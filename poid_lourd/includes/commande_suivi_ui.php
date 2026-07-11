<?php
/**
 * Bloc UI « Suivi commande » (timeline + carte live)
 * Réutilisable côté client (commande-categorie.php) et admin (commandes/details.php).
 * Programmation procédurale uniquement
 */

if (!function_exists('commande_suivi_format_phone_display')) {
    /**
     * Affiche un numéro avec indicatif international (+).
     */
    function commande_suivi_format_phone_display(string $tel): string
    {
        $tel = trim($tel);
        if ($tel === '') {
            return '';
        }
        if (strpos($tel, '+') === 0) {
            return $tel;
        }
        $digits = preg_replace('/\D/', '', $tel);
        if ($digits === '') {
            return $tel;
        }
        return '+' . $digits;
    }
}

if (!function_exists('commande_suivi_statut_rank')) {
    /**
     * Rang absolu pour le suivi chronologique (plus = plus avancé).
     */
    function commande_suivi_statut_rank(string $statut): int
    {
        static $rank = [
            'annulee' => -1,
            'en_attente' => 1,
            'confirmee' => 2,
            'prise_en_charge' => 3,
            'en_preparation' => 4,
            'expediee' => 5,
            'livraison_en_cours' => 6,
            'livree' => 7,
            'paye' => 8,
        ];
        return $rank[$statut] ?? 0;
    }
}

if (!function_exists('commande_suivi_compute_metrics')) {
    /**
     * Métriques affichées dans la carte live et la timeline.
     *
     * @param array<string, mixed> $commande
     * @return array{
     *   st: string,
     *   rank: int,
     *   dt_cmd: int|false|null,
     *   dt_liv: int|false|null,
     *   timeline_rows: array<int, array{done: bool, current: bool, label: string, desc: string, time: string}>,
     *   livreligne_pourcentage: int|string,
     *   svg_ring_off: float|int|string,
     *   live_pair: array{0: string, 1: string}
     * }
     */
    function commande_suivi_compute_metrics(array $commande): array
    {
        $st = (string) ($commande['statut'] ?? '');
        $rank = commande_suivi_statut_rank($st);
        $dt_cmd = isset($commande['date_commande']) ? strtotime((string) $commande['date_commande']) : null;
        $dt_liv = !empty($commande['date_livraison']) ? strtotime((string) $commande['date_livraison']) : null;

        $lib_statut_live = [
            'en_attente' => ['En attente de validation', 'Votre commande est bien enregistrée.'],
            'confirmee' => ['Commande confirmée', 'Le marchand prépare vos articles.'],
            'prise_en_charge' => ['Prise en charge', 'Préparation de votre colis.'],
            'en_preparation' => ['En préparation', 'Empaquetage en cours…'],
            'expediee' => ['Colis envoyé', 'Handoff au transporteur.'],
            'livraison_en_cours' => ['En cours de livraison', 'Votre colis est sur la route vers vous.'],
            'livree' => ['Livraison effectuée', 'Le colis a été déposé.'],
            'paye' => ['Réception confirmée', 'Merci ! Vous avez confirmé la bonne réception.'],
            'annulee' => ['Commande annulée', 'Cette commande a été annulée.'],
        ];
        $live_pair = $lib_statut_live[$st] ?? ['Suivi commande', 'Statut mis à jour.'];

        /** @var array<int, array{done:bool,current:bool,label:string,desc:string,time:string}> $timeline_rows */
        $timeline_rows = [];
        $pct_num = 35;

        if ($st !== 'annulee') {
            $d1 = ($rank >= 2);
            $d2 = ($rank >= 5);
            $d3 = in_array($st, ['livree', 'paye'], true);
            $d4 = ($st === 'paye');

            $c1 = !$d1;
            $c2 = ($d1 && !$d2);
            $c3 = ($d2 && !$d3);
            $c4 = ($d3 && !$d4 && $st === 'livree');

            $desc_recv = (($st === 'livraison_en_cours'))
                ? 'Dès réception physique : utilisez le bouton « Confirmer la réception » en bas.'
                : 'Une fois le colis reçu, confirmez sur cette page lorsque disponible.';

            $timeline_rows = [
                [
                    'done' => $d1,
                    'current' => $c1,
                    'label' => 'Commande validée',
                    'desc' => 'Votre commande est enregistrée côté marchand.',
                    'time' => (($dt_cmd && ($d1 || $c1)) ? date('H:i', $dt_cmd) : ''),
                ],
                [
                    'done' => $d2,
                    'current' => $c2,
                    'label' => 'Préparation',
                    'desc' => 'Empaquetage avant expédition.',
                    'time' => '',
                ],
                [
                    'done' => $d3,
                    'current' => $c3,
                    'label' => 'Envoi et livraison',
                    'desc' => 'Expédition jusqu’à remise chez vous ou au lieu convenu.',
                    'time' => '',
                ],
                [
                    'done' => $d4,
                    'current' => $c4,
                    'label' => 'Réception confirmée',
                    'desc' => $desc_recv,
                    'time' => '',
                ],
            ];

            if ($d4 && (($dt_liv ?: $dt_cmd))) {
                $timeline_rows[3]['time'] = date('H:i', $dt_liv ?: $dt_cmd);
            }

            $completed = 0;
            foreach ($timeline_rows as $tr) {
                if (!empty($tr['done'])) {
                    ++$completed;
                }
            }
            $n = max(1, count($timeline_rows));
            $has_cur = false;
            foreach ($timeline_rows as $tr) {
                if (!empty($tr['current'])) {
                    $has_cur = true;
                    break;
                }
            }
            $pct_float = (($completed + ($has_cur ? 0.45 : 0)) / $n) * 100;
            $pct_num = max(8, min(100, (int) round($pct_float)));
            if (in_array($st, ['livree', 'paye'], true)) {
                $pct_num = 100;
            }
        }

        $livreligne_pourcentage = $pct_num;
        $svg_ring_off = round(max(0, min(100, 100 - (int) $livreligne_pourcentage)), 2);

        if ($rank === -1) {
            $livreligne_pourcentage = 0;
            $svg_ring_off = 100;
        }

        return [
            'st' => $st,
            'rank' => $rank,
            'dt_cmd' => $dt_cmd,
            'dt_liv' => $dt_liv,
            'timeline_rows' => $timeline_rows,
            'livreligne_pourcentage' => $livreligne_pourcentage,
            'svg_ring_off' => $svg_ring_off,
            'live_pair' => $live_pair,
        ];
    }
}

if (!function_exists('commande_suivi_render_dashboard')) {
    /**
     * @param array<string, mixed> $commande
     * @param array{
     *   commande_id?: int,
     *   suivi_confirm_success?: bool,
     *   suivi_confirm_error?: bool,
     *   show_client_actions_bar?: bool,
     *   wrap_class?: string,
     *   admin_hint?: bool,
     *   admin_contact_in_live?: bool,
     *   admin_compact_meta_row?: bool,
     *   slot_after_suivi_body_html?: string
     * } $options
     */
    function commande_suivi_render_dashboard(array $commande, array $options = []): void
    {
        $commande_id = isset($options['commande_id']) ? (int) $options['commande_id'] : 0;
        $suivi_confirm_success = !empty($options['suivi_confirm_success']);
        $suivi_confirm_error = !empty($options['suivi_confirm_error']);
        $show_client_actions_bar = array_key_exists('show_client_actions_bar', $options)
            ? !empty($options['show_client_actions_bar'])
            : true;
        $wrap_class = isset($options['wrap_class'])
            ? (string) $options['wrap_class']
            : 'cc-products-anchor commande-suivi-detail';
        $admin_hint = !empty($options['admin_hint']);
        $admin_contact_in_live = !empty($options['admin_contact_in_live']);
        $admin_compact_meta_row = !empty($options['admin_compact_meta_row']);
        $slot_after_suivi = isset($options['slot_after_suivi_body_html'])
            ? (string) $options['slot_after_suivi_body_html']
            : '';
        $slot_replace_progress = isset($options['slot_replace_progress_html'])
            ? (string) $options['slot_replace_progress_html']
            : '';

        $m = commande_suivi_compute_metrics($commande);
        $st = $m['st'];
        $dt_cmd = $m['dt_cmd'];
        $timeline_rows = $m['timeline_rows'];
        $livreligne_pourcentage = $m['livreligne_pourcentage'];
        $svg_ring_off = $m['svg_ring_off'];
        $live_pair = $m['live_pair'];

        $num_fallback = '#' . ($commande_id > 0 ? $commande_id : '');

        echo '<div class="' . htmlspecialchars($wrap_class, ENT_QUOTES, 'UTF-8') . '">';
        ?>
            <?php if ($admin_hint): ?>
            <p class="cc-admin-hint-msg" role="note">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                Aperçu du suivi comme côté client. Pour modifier le statut, utilisez la section « Statut &amp; actions » ci-dessous.
            </p>
            <?php endif; ?>

            <div class="cc-meta-row" style="display:none;" aria-hidden="true">
                <span class="cc-num-cmd"><?php echo htmlspecialchars($commande['numero_commande'] ?? $num_fallback, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if (!$admin_compact_meta_row): ?>
                <span>· <?php echo $dt_cmd ? date('d/m/Y à H:i', $dt_cmd) : 'Date inconnue'; ?></span>
                <?php endif; ?>
            </div>

            <?php if ($st === 'annulee'): ?>
            <div class="cc-cancel-banner" role="status">
                <strong><i class="fas fa-ban"></i> Commande annulée</strong>
                <p style="margin:0.65rem 0 0;line-height:1.45;font-size:.88rem">Cette commande n’est plus suivie.</p>
            </div>
            <?php else: ?>
            <div class="cc-live-card" style="<?php echo '--cc-live-pct: ' .
                htmlspecialchars((string) $livreligne_pourcentage, ENT_QUOTES, 'UTF-8') .
                '%; --cc-live-pct-num: ' .
                htmlspecialchars((string) $livreligne_pourcentage, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="cc-live-badge"><span class="cc-live-dot" aria-hidden="true"></span>Suivi en direct</span>
                <div class="cc-live-row">
                    <div class="cc-live-copy">
                        <h2 class="cc-live-status"><?php echo htmlspecialchars($live_pair[0], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <div class="cc-live-bar-track"><div class="cc-live-bar-fill" role="presentation"></div></div>
                    </div>
                    <div class="cc-live-ring-wrap">
                        <svg class="cc-live-ring-svg" viewBox="0 0 100 100" aria-hidden="true">
                            <circle class="cc-live-ring-bg" cx="50" cy="50" r="40" />
                            <circle class="cc-live-ring-fg"
                                cx="50" cy="50" r="40"
                                pathLength="100"
                                stroke-dasharray="100"
                                stroke-dashoffset="<?php echo htmlspecialchars((string) $svg_ring_off, ENT_QUOTES, 'UTF-8'); ?>" />
                        </svg>
                        <span class="cc-live-icon-disk"><i class="fas fa-truck-fast" aria-hidden="true"></i></span>
                    </div>
                </div>
                <?php if ($admin_contact_in_live):
                    if (!function_exists('commande_is_retrait')) {
                        require_once __DIR__ . '/commande_mode_helpers.php';
                    }
                    $cmd_suivi_is_retrait = commande_is_retrait($commande);
                    $cnom = trim(($commande['user_prenom'] ?? '') . ' ' . ($commande['user_nom'] ?? ''));
                    if ($cnom === '') {
                        $cnom = '—';
                    }
                    $ctel_liv = commande_suivi_format_phone_display((string) ($commande['telephone_livraison'] ?? ''));
                    ?>
                <div class="cc-live-admin-contact" aria-label="Coordonnées commande">
                    <div class="cc-live-admin-contact__grid">
                        <div class="cc-live-admin-contact__cell">
                            <span class="cc-live-admin-contact__lab"><i class="fas fa-user" aria-hidden="true"></i> Client</span>
                            <span class="cc-live-admin-contact__val"><?php echo htmlspecialchars($cnom, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="cc-live-admin-contact__cell">
                            <span class="cc-live-admin-contact__lab"><i class="fas fa-headset" aria-hidden="true"></i> <?php echo $cmd_suivi_is_retrait ? 'Tél. contact' : 'Tél. livraison'; ?></span>
                            <span class="cc-live-admin-contact__val"><?php echo $ctel_liv !== '' ? htmlspecialchars($ctel_liv, ENT_QUOTES, 'UTF-8') : '—'; ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($slot_replace_progress !== ''): ?>
            <?php echo $slot_replace_progress; ?>
            <?php else: ?>
            <div class="cc-progress-card">
                <p class="cc-progress-title">Progression</p>
                <ul class="cc-timeline" aria-label="Étapes de la livraison">
                    <?php
                    foreach ($timeline_rows as $tr):
                        $cls = $tr['done'] ? 'done' : ($tr['current'] ? 'current' : 'pending');
                        ?>
                    <li class="cc-step cc-step--<?php echo htmlspecialchars($cls, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="cc-step-mark" aria-hidden="true">
                            <?php if ($cls === 'done'): ?>
                                <i class="fas fa-check"></i>
                            <?php elseif ($cls === 'current'): ?>
                                <span class="cc-step-dot"></span>
                            <?php endif; ?>
                        </span>
                        <div class="cc-step-inner">
                            <div class="cc-step-top">
                                <h3 class="cc-step-label"><?php echo htmlspecialchars($tr['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <div class="cc-step-meta">
                                    <?php if ($tr['done']): ?>
                                        <span class="cc-pill cc-pill--done">Fait</span>
                                    <?php elseif ($tr['current']): ?>
                                        <span class="cc-pill cc-pill--now">En cours</span>
                                    <?php endif; ?>
                                    <?php if ($tr['time'] !== ''): ?>
                                        <span class="cc-step-time"><?php echo htmlspecialchars($tr['time'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="cc-step-desc"><?php echo htmlspecialchars($tr['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($slot_after_suivi !== ''): ?>
            <?php echo $slot_after_suivi; ?>
            <?php endif; ?>

            <?php if ($show_client_actions_bar): ?>
            <div class="cc-actions-bar">
                <?php if ($st === 'livraison_en_cours'): ?>
                    <form method="post"
                        action="<?php echo htmlspecialchars(
                            'commande-categorie.php?commande_id=' . (int) $commande_id,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>">
                        <input type="hidden" name="commande_id" value="<?php echo (int) $commande_id; ?>">
                        <button type="submit" name="confirmer_livraison" value="1" class="cc-btn-primary">
                            <i class="fas fa-hand-holding-heart"></i> Confirmer la réception
                        </button>
                    </form>
                <?php elseif (in_array($st, ['paye', 'livree'], true)): ?>
                    <button type="button" class="cc-btn-primary cc-btn-primary--success" disabled>
                        <i class="fas fa-check"></i>
                        <?php echo $st === 'paye' ? 'Réception déjà confirmée' : 'Livraison enregistrée'; ?>
                    </button>
                <?php elseif ($st !== 'annulee'): ?>
                    <button type="button" class="cc-btn-primary" disabled aria-disabled="true">
                        Confirmation disponible après livraison en cours
                    </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php
        echo '</div>';
    }
}
