<?php
/**
 * Fonctions utilitaires pour les migrations BDD (idempotentes, sans suppression de données).
 */

if (!function_exists('mig_connect')) {
    function mig_connect() {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        $db = null;
        require __DIR__ . '/../../conn/conn.php';
        if (!$db instanceof PDO) {
            fwrite(STDERR, "Connexion BDD impossible. Vérifiez conn/conn.php\n");
            exit(1);
        }
        $db->exec('SET NAMES utf8mb4');
        $pdo = $db;
        return $pdo;
    }
}

if (!function_exists('mig_is_duplicate_error')) {
    function mig_is_duplicate_error(PDOException $e) {
        $m = strtolower($e->getMessage());
        $codes = ['23000', '42000', 'HY000', '42S21'];
        if (in_array((string) $e->getCode(), $codes, true) && (
            strpos($m, 'duplicate') !== false
            || strpos($m, 'already exists') !== false
            || strpos($m, 'déjà') !== false
            || strpos($m, 'deja') !== false
            || strpos($m, 'exists') !== false
            || strpos($m, 'multiple primary key') !== false
            || strpos($m, 'déjà utilisé') !== false
            || strpos($m, 'deja utilise') !== false
        )) {
            return true;
        }
        return false;
    }
}

if (!function_exists('mig_table_exists')) {
    function mig_table_exists(PDO $db, $table) {
        $q = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $q->execute([(string) $table]);
        return (int) $q->fetchColumn() > 0;
    }
}

if (!function_exists('mig_column_exists')) {
    function mig_column_exists(PDO $db, $table, $column) {
        $q = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $q->execute([(string) $table, (string) $column]);
        return (int) $q->fetchColumn() > 0;
    }
}

if (!function_exists('mig_index_exists')) {
    function mig_index_exists(PDO $db, $table, $index) {
        $q = $db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $q->execute([(string) $table, (string) $index]);
        return (int) $q->fetchColumn() > 0;
    }
}

if (!function_exists('mig_safe_exec')) {
    function mig_safe_exec(PDO $db, $sql, $label = '') {
        try {
            $db->exec($sql);
            if ($label !== '') {
                echo "  + $label\n";
            }
            return true;
        } catch (PDOException $e) {
            if (mig_is_duplicate_error($e)) {
                if ($label !== '') {
                    echo "  — $label (déjà présent)\n";
                }
                return true;
            }
            throw $e;
        }
    }
}

if (!function_exists('mig_add_column_if_missing')) {
    function mig_add_column_if_missing(PDO $db, $table, $column, $definition) {
        if (mig_column_exists($db, $table, $column)) {
            echo "  — $table.$column (déjà présent)\n";
            return false;
        }
        mig_safe_exec($db, "ALTER TABLE `$table` ADD COLUMN `$column` $definition", "$table.$column");
        return true;
    }
}

if (!function_exists('mig_add_column_smart')) {
    /**
     * Ajoute une colonne en choisissant la première ancre AFTER disponible.
     */
    function mig_add_column_smart(PDO $db, $table, $column, $definition, array $after_candidates = []) {
        if (mig_column_exists($db, $table, $column)) {
            echo "  — $table.$column (déjà présent)\n";
            return false;
        }
        if (!mig_table_exists($db, $table)) {
            echo "  ! table $table absente pour $column\n";
            return false;
        }
        $def = trim($definition);
        foreach ($after_candidates as $anchor) {
            if (mig_column_exists($db, $table, $anchor)) {
                mig_safe_exec(
                    $db,
                    "ALTER TABLE `$table` ADD COLUMN `$column` $def AFTER `$anchor`",
                    "$table.$column"
                );
                return true;
            }
        }
        mig_safe_exec($db, "ALTER TABLE `$table` ADD COLUMN `$column` $def", "$table.$column");
        return true;
    }
}

if (!function_exists('mig_scan_gaps')) {
    function mig_scan_gaps(PDO $db) {
        $expectations = require __DIR__ . '/../schema_expectations.php';
        $missing_tables = [];
        $missing_columns = [];
        foreach ($expectations['tables'] as $table) {
            if (!mig_table_exists($db, $table)) {
                $missing_tables[] = $table;
            }
        }
        foreach ($expectations['columns'] as $item) {
            $table = $item[0];
            $column = $item[1];
            if (!mig_table_exists($db, $table)) {
                $missing_columns[] = ['table' => $table, 'column' => $column];
                continue;
            }
            if (!mig_column_exists($db, $table, $column)) {
                $missing_columns[] = ['table' => $table, 'column' => $column];
            }
        }
        return [
            'missing_tables' => $missing_tables,
            'missing_columns' => $missing_columns,
        ];
    }
}

if (!function_exists('mig_strip_sql_leading_comments')) {
    /**
     * Retire les lignes de commentaire SQL en tête de bloc (-- …).
     * Ne supprime pas le CREATE/ALTER qui suit un commentaire d'en-tête.
     */
    function mig_strip_sql_leading_comments($sql) {
        $lines = preg_split('/\R/', (string) $sql);
        $out = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || strpos($trim, '--') === 0) {
                continue;
            }
            $out[] = $line;
        }
        return trim(implode("\n", $out));
    }
}

if (!function_exists('mig_strip_inline_foreign_keys')) {
    /**
     * Retire les CONSTRAINT … FOREIGN KEY inline d'un CREATE TABLE.
     */
    function mig_strip_inline_foreign_keys($sql) {
        return preg_replace(
            '/,?\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN\s+KEY\s*\([^)]+\)\s+REFERENCES\s+`[^`]+`\s*\([^)]+\)(?:\s+ON\s+DELETE\s+(?:RESTRICT|CASCADE|SET\s+NULL|NO\s+ACTION))?(?:\s+ON\s+UPDATE\s+(?:RESTRICT|CASCADE|SET\s+NULL|NO\s+ACTION))?/i',
            '',
            (string) $sql
        );
    }
}

if (!function_exists('mig_exec_sql_file')) {
    /**
     * @param bool $strip_fk Retire les FK inline (CREATE TABLE sans contrainte bloquante).
     */
    function mig_exec_sql_file(PDO $db, $path, $label = '', $strip_fk = false) {
        if (!is_file($path)) {
            echo "! Fichier SQL absent : $path\n";
            return false;
        }
        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            echo "! Fichier SQL vide : $path\n";
            return false;
        }
        $parts = preg_split('/;\s*\n/', $sql);
        $applied = 0;
        $skipped = 0;
        foreach ($parts as $stmt) {
            $stmt = mig_strip_sql_leading_comments(trim($stmt));
            if ($stmt === '') {
                continue;
            }
            if ($strip_fk) {
                $stmt = mig_strip_inline_foreign_keys($stmt);
            }
            try {
                $db->exec($stmt);
                $applied++;
            } catch (PDOException $e) {
                if (mig_is_duplicate_error($e)) {
                    $skipped++;
                    continue;
                }
                throw $e;
            }
        }
        $name = $label !== '' ? $label : basename($path);
        if ($applied > 0) {
            echo "+ $name ($applied instruction(s))\n";
        } elseif ($skipped > 0) {
            echo "— $name (déjà appliqué)\n";
        } else {
            echo "— $name (aucune instruction)\n";
        }
        return true;
    }
}

if (!function_exists('mig_ensure_tracking_table')) {
    function mig_ensure_tracking_table(PDO $db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `_schema_migrations` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `migration_id` VARCHAR(120) NOT NULL,
                `label` VARCHAR(255) NOT NULL DEFAULT '',
                `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_migration_id` (`migration_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('mig_is_applied')) {
    function mig_is_applied(PDO $db, $migration_id) {
        if (!mig_table_exists($db, '_schema_migrations')) {
            return false;
        }
        $q = $db->prepare('SELECT COUNT(*) FROM `_schema_migrations` WHERE migration_id = ?');
        $q->execute([(string) $migration_id]);
        return (int) $q->fetchColumn() > 0;
    }
}

if (!function_exists('mig_mark_applied')) {
    function mig_mark_applied(PDO $db, $migration_id, $label = '') {
        mig_ensure_tracking_table($db);
        $q = $db->prepare('
            INSERT INTO `_schema_migrations` (migration_id, label)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE label = VALUES(label), applied_at = CURRENT_TIMESTAMP
        ');
        $q->execute([(string) $migration_id, (string) $label]);
    }
}

if (!function_exists('mig_run_php_script')) {
    function mig_run_php_script($script_path) {
        if (!is_file($script_path)) {
            echo "! Script absent : $script_path\n";
            return 1;
        }
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script_path);
        passthru($cmd, $code);
        return (int) $code;
    }
}

if (!function_exists('mig_get_column_type')) {
    function mig_get_column_type(PDO $db, $table, $column) {
        if (!mig_column_exists($db, $table, $column)) {
            return '';
        }
        $q = $db->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
        $q->execute([(string) $column]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return isset($row['Type']) ? (string) $row['Type'] : '';
    }
}

if (!function_exists('mig_try_add_foreign_key')) {
    function mig_try_add_foreign_key(PDO $db, $table, $constraint, $sql) {
        try {
            $check = $db->prepare("
                SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
            ");
            $check->execute([(string) $table, (string) $constraint]);
            if ((int) $check->fetchColumn() > 0) {
                echo "  — FK $constraint (déjà présente)\n";
                return true;
            }
            $db->exec($sql);
            echo "  + FK $constraint\n";
            return true;
        } catch (PDOException $e) {
            if (mig_is_duplicate_error($e)) {
                echo "  — FK $constraint (déjà présente)\n";
                return true;
            }
            echo "  ! FK $constraint ignorée : " . $e->getMessage() . "\n";
            return false;
        }
    }
}

if (!function_exists('mig_enum_values')) {
    function mig_enum_values(PDO $db, $table, $column) {
        if (!mig_column_exists($db, $table, $column)) {
            return [];
        }
        $q = $db->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
        $q->execute([(string) $column]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['Type']) || !preg_match("/^enum\\('(.+)'\\)$/i", $row['Type'], $m)) {
            return [];
        }
        $parts = explode("','", $m[1]);
        return array_map(static function ($v) {
            return str_replace("''", "'", $v);
        }, $parts);
    }
}

if (!function_exists('mig_distinct_column_values')) {
    function mig_distinct_column_values(PDO $db, $table, $column) {
        if (!mig_table_exists($db, $table) || !mig_column_exists($db, $table, $column)) {
            return [];
        }
        $sql = 'SELECT DISTINCT `' . str_replace('`', '``', $column) . '` FROM `' . str_replace('`', '``', $table) . '` WHERE `' . str_replace('`', '``', $column) . '` IS NOT NULL';
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('mig_expand_enum_column')) {
    /**
     * Élargit un ENUM sans supprimer les valeurs déjà utilisées en base.
     */
    function mig_expand_enum_column(PDO $db, $table, $column, array $required_values, $default = null) {
        if (!mig_column_exists($db, $table, $column)) {
            echo "  ! $table.$column introuvable\n";
            return false;
        }

        $q = $db->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
        $q->execute([(string) $column]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['Type']) || stripos($row['Type'], 'enum(') !== 0) {
            echo "  ! $table.$column n'est pas un ENUM\n";
            return false;
        }

        $current = mig_enum_values($db, $table, $column);
        $in_use = mig_distinct_column_values($db, $table, $column);
        $ordered = [];
        foreach ($required_values as $value) {
            if (!in_array($value, $ordered, true)) {
                $ordered[] = $value;
            }
        }
        foreach (array_merge($current, $in_use) as $value) {
            if ($value !== null && $value !== '' && !in_array($value, $ordered, true)) {
                $ordered[] = $value;
            }
        }

        $missing = array_diff($required_values, $current);
        if (empty($missing) && count($ordered) === count($current)) {
            echo "  — $table.$column ENUM déjà à jour\n";
            return true;
        }

        if ($default === null) {
            $default = isset($row['Default']) && $row['Default'] !== '' ? $row['Default'] : ($ordered[0] ?? '');
        }
        if (!in_array($default, $ordered, true)) {
            $default = $ordered[0] ?? 'admin';
        }

        $enum_sql = "'" . implode("','", array_map(static function ($v) {
            return str_replace("'", "''", $v);
        }, $ordered)) . "'";
        $default_sql = str_replace("'", "''", (string) $default);
        $nullable = isset($row['Null']) && strtoupper((string) $row['Null']) === 'YES';
        $null_sql = $nullable ? 'NULL' : 'NOT NULL';

        $sql = "ALTER TABLE `$table` MODIFY COLUMN `$column` ENUM($enum_sql) $null_sql DEFAULT '$default_sql'";
        mig_safe_exec($db, $sql, "$table.$column ENUM élargi");
        return true;
    }
}

if (!function_exists('mig_parse_cli_args')) {
    function mig_parse_cli_args($argv) {
        $opts = [
            'dry_run' => false,
            'force' => false,
            'scan' => false,
            'only' => '',
            'from' => '',
            'continue' => false,
            'repair' => false,
        ];
        if (!is_array($argv)) {
            return $opts;
        }
        foreach ($argv as $i => $arg) {
            if ($arg === '--dry-run') {
                $opts['dry_run'] = true;
            } elseif ($arg === '--force') {
                $opts['force'] = true;
            } elseif ($arg === '--scan') {
                $opts['scan'] = true;
            } elseif (strpos($arg, '--only=') === 0) {
                $opts['only'] = substr($arg, 7);
            } elseif ($arg === '--only' && isset($argv[$i + 1])) {
                $opts['only'] = (string) $argv[$i + 1];
            } elseif (strpos($arg, '--from=') === 0) {
                $opts['from'] = substr($arg, 6);
            } elseif ($arg === '--from' && isset($argv[$i + 1])) {
                $opts['from'] = (string) $argv[$i + 1];
            } elseif ($arg === '--continue') {
                $opts['continue'] = true;
            } elseif ($arg === '--repair') {
                $opts['repair'] = true;
            }
        }
        return $opts;
    }
}
