<?php
/**
 * Recherche produit par nom : insensible à la casse, accents, fautes légères.
 * (Même logique de score que geo_geocode_suggest_relevance_score, appliquée au nom.)
 */

if (!function_exists('produit_recherche_normalize')) {
    function produit_recherche_normalize($text)
    {
        if (function_exists('geo_geocode_suggest_normalize')) {
            return geo_geocode_suggest_normalize($text);
        }
        $text = mb_strtolower(trim((string) $text), 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        if ($text === '') {
            return '';
        }
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($ascii !== false && $ascii !== '') {
                $text = mb_strtolower($ascii, 'UTF-8');
            }
        }
        $text = str_replace(["'", '`', '’'], ' ', $text);
        $text = preg_replace('/[^a-z0-9\s\-]/u', ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}

if (!function_exists('produit_recherche_min_score')) {
    function produit_recherche_min_score()
    {
        return 65;
    }
}

if (!function_exists('produit_recherche_nom_score')) {
    /**
     * @return int Score 0 = pas de correspondance
     */
    function produit_recherche_nom_score($query, $nom)
    {
        $q = produit_recherche_normalize($query);
        if ($q === '') {
            return 0;
        }

        $l = produit_recherche_normalize($nom);
        if ($l === '') {
            return 0;
        }

        if ($l === $q) {
            return 1000;
        }
        if (strpos($l, $q) === 0) {
            return 850;
        }
        if (strpos($l, $q) !== false) {
            return 700;
        }

        $score = 0;
        $words = array_filter(explode(' ', $q), static function ($w) {
            return mb_strlen($w) >= 2;
        });

        foreach ($words as $word) {
            if (strpos($l, $word) !== false) {
                $score += 120;
                continue;
            }
            if (strlen($word) >= 3) {
                similar_text($word, $l, $pct);
                if ($pct >= 72) {
                    $score += (int) round($pct);
                    continue;
                }
            }
            if (strlen($word) >= 3 && produit_recherche_word_in_name_fuzzy($word, $l)) {
                $score += 95;
            }
        }

        if ($score === 0 && mb_strlen($q) >= 3) {
            similar_text($q, $l, $pctFull);
            if ($pctFull >= 68) {
                $score = (int) round($pctFull);
            }
        }

        return $score;
    }
}

if (!function_exists('produit_recherche_word_in_name_fuzzy')) {
    /**
     * Mot présent dans le nom (token ou sous-chaîne proche).
     */
    function produit_recherche_word_in_name_fuzzy($word, $normalizedName)
    {
        $tokens = array_filter(explode(' ', $normalizedName));
        foreach ($tokens as $token) {
            if ($token === $word || strpos($token, $word) !== false || strpos($word, $token) !== false) {
                return true;
            }
            if (strlen($word) >= 4 && strlen($token) >= 4) {
                similar_text($word, $token, $pct);
                if ($pct >= 78) {
                    return true;
                }
                if (produit_recherche_levenshtein_ok($word, $token)) {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('produit_recherche_levenshtein_ok')) {
    function produit_recherche_levenshtein_ok($a, $b)
    {
        $a = (string) $a;
        $b = (string) $b;
        $len = max(strlen($a), strlen($b));
        if ($len < 4) {
            return false;
        }
        $maxDist = $len <= 5 ? 1 : ($len <= 8 ? 2 : 3);
        return levenshtein($a, $b) <= $maxDist;
    }
}

if (!function_exists('produit_recherche_nom_matches')) {
    function produit_recherche_nom_matches($query, $nom)
    {
        return produit_recherche_nom_score($query, $nom) >= produit_recherche_min_score();
    }
}

if (!function_exists('produit_recherche_filter_sort_rows')) {
    /**
     * Filtre par nom (fuzzy) et trie par score décroissant puis nom.
     *
     * @param array $rows
     * @param string $query
     * @param string $nomKey
     * @param int|null $limit
     * @return array
     */
    function produit_recherche_filter_sort_rows(array $rows, $query, $nomKey = 'nom', $limit = null)
    {
        $query = trim((string) $query);
        if ($query === '') {
            if ($limit !== null && $limit > 0) {
                return array_slice($rows, 0, (int) $limit);
            }
            return $rows;
        }

        $min = produit_recherche_min_score();
        $scored = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $nom = isset($row[$nomKey]) ? (string) $row[$nomKey] : '';
            $score = produit_recherche_nom_score($query, $nom);
            if ($score < $min) {
                continue;
            }
            $row['_search_score'] = $score;
            $scored[] = $row;
        }

        usort($scored, static function ($a, $b) {
            $sa = (int) ($a['_search_score'] ?? 0);
            $sb = (int) ($b['_search_score'] ?? 0);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            $na = mb_strtolower((string) ($a['nom'] ?? ''), 'UTF-8');
            $nb = mb_strtolower((string) ($b['nom'] ?? ''), 'UTF-8');
            return strcmp($na, $nb);
        });

        foreach ($scored as $i => $row) {
            unset($scored[$i]['_search_score']);
        }

        if ($limit !== null && $limit > 0) {
            return array_slice($scored, 0, (int) $limit);
        }

        return $scored;
    }
}

if (!function_exists('produit_recherche_count_matching_rows')) {
    function produit_recherche_count_matching_rows(array $rows, $query, $nomKey = 'nom')
    {
        return count(produit_recherche_filter_sort_rows($rows, $query, $nomKey, null));
    }
}

if (!function_exists('produit_recherche_apply_tri')) {
    /**
     * Tri catalogue après filtrage fuzzy (prix / nom).
     */
    function produit_recherche_apply_tri(array $rows, $tri, $rand_seed = 0)
    {
        if ($tri === 'prix_asc' || $tri === 'prix_desc') {
            usort($rows, static function ($a, $b) use ($tri) {
                $pa = produit_recherche_effective_price($a);
                $pb = produit_recherche_effective_price($b);
                if ($pa === $pb) {
                    return strcmp(
                        mb_strtolower((string) ($a['nom'] ?? ''), 'UTF-8'),
                        mb_strtolower((string) ($b['nom'] ?? ''), 'UTF-8')
                    );
                }
                return $tri === 'prix_asc' ? ($pa <=> $pb) : ($pb <=> $pa);
            });
        } elseif ($tri === 'nom') {
            usort($rows, static function ($a, $b) {
                return strcmp(
                    mb_strtolower((string) ($a['nom'] ?? ''), 'UTF-8'),
                    mb_strtolower((string) ($b['nom'] ?? ''), 'UTF-8')
                );
            });
        } elseif ($tri === 'date') {
            usort($rows, static function ($a, $b) {
                $da = strtotime($a['date_creation'] ?? '') ?: 0;
                $db = strtotime($b['date_creation'] ?? '') ?: 0;
                return $db <=> $da;
            });
        }
        return $rows;
    }
}

if (!function_exists('produit_recherche_effective_price')) {
    function produit_recherche_effective_price(array $row)
    {
        $prix = isset($row['prix']) ? (float) $row['prix'] : 0;
        $promo = isset($row['prix_promotion']) ? (float) $row['prix_promotion'] : 0;
        if ($promo > 0 && $promo < $prix) {
            return $promo;
        }
        return $prix;
    }
}
