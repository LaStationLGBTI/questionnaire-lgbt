<?php
// Gestion des clés d'accès au site : génération (avec durée de vie), prolongation,
// révocation / réactivation, suppression. Authentification admin commune : auth.php.
// La vérification côté visiteurs est dans access.php (table access_keys).
require_once 'auth.php';
require_once 'access.php';
$login_error = admin_handle_auth();

$message = '';
$message_type = 'success';
$new_key_display = null; // clé fraîchement générée, mise en avant

/** Convertit (valeur, unité) en secondes. Unités : hours / days. */
function keys_duration_seconds($value, $unit) {
    $value = (int) $value;
    if ($value < 1) return 0;
    return $unit === 'hours' ? $value * 3600 : $value * 86400;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && admin_is_logged_in()
    && (isset($_POST['generate']) || isset($_POST['extend']) || isset($_POST['revoke'])
        || isset($_POST['reactivate']) || isset($_POST['delete']))) {
    admin_require_csrf();
    try {
        $pdo = access_pdo();
        access_ensure_table($pdo);

        if (isset($_POST['generate'])) {
            $seconds = keys_duration_seconds(isset($_POST['duration_value']) ? $_POST['duration_value'] : 0,
                                             isset($_POST['duration_unit']) ? $_POST['duration_unit'] : 'days');
            if ($seconds <= 0) {
                $message = "Durée invalide."; $message_type = 'error';
            } else {
                $label = trim(isset($_POST['label']) ? (string) $_POST['label'] : '');
                // Génère jusqu'à trouver une clé libre (collision quasi impossible sur 12 caractères).
                for ($try = 0; $try < 10; $try++) {
                    $key = access_generate_key();
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM access_keys WHERE access_key = ?");
                    $stmt->execute([$key]);
                    if (!$stmt->fetchColumn()) break;
                    $key = null;
                }
                if ($key === null) throw new Exception('key_alloc');
                $pdo->prepare("INSERT INTO access_keys (access_key, label, created_at, expires_at)
                               VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))")
                    ->execute([$key, $label, $seconds]);
                $new_key_display = access_format_key($key);
                $message = "Clé générée : <strong style=\"font-size:1.15em; letter-spacing:1px;\">"
                         . htmlspecialchars($new_key_display) . "</strong> — transmettez-la telle quelle.";
            }
        } elseif (isset($_POST['extend'])) {
            $seconds = keys_duration_seconds(isset($_POST['duration_value']) ? $_POST['duration_value'] : 0,
                                             isset($_POST['duration_unit']) ? $_POST['duration_unit'] : 'days');
            if ($seconds <= 0) {
                $message = "Durée invalide."; $message_type = 'error';
            } else {
                // Prolonge depuis l'expiration si la clé est encore valide, sinon depuis maintenant.
                $stmt = $pdo->prepare("UPDATE access_keys
                                       SET expires_at = DATE_ADD(GREATEST(expires_at, NOW()), INTERVAL ? SECOND)
                                       WHERE id = ?");
                $stmt->execute([$seconds, (int) $_POST['key_id']]);
                $message = $stmt->rowCount() ? "Clé prolongée." : "Clé introuvable.";
                if (!$stmt->rowCount()) $message_type = 'error';
            }
        } elseif (isset($_POST['revoke'])) {
            $pdo->prepare("UPDATE access_keys SET revoked = 1 WHERE id = ?")->execute([(int) $_POST['key_id']]);
            $message = "Clé révoquée : l'accès sera coupé à la prochaine vérification (≤ 3 min).";
        } elseif (isset($_POST['reactivate'])) {
            $pdo->prepare("UPDATE access_keys SET revoked = 0 WHERE id = ?")->execute([(int) $_POST['key_id']]);
            $message = "Clé réactivée.";
        } elseif (isset($_POST['delete'])) {
            $pdo->prepare("DELETE FROM access_keys WHERE id = ?")->execute([(int) $_POST['key_id']]);
            $message = "Clé supprimée définitivement.";
        }
    } catch (Exception $e) {
        error_log('[manage_keys] ' . $e->getMessage());
        $message = "Erreur lors de l'opération."; $message_type = 'error';
    }
}

// Liste des clés pour l'affichage.
$keys = [];
$list_error = '';
if (admin_is_logged_in()) {
    try {
        $pdo = access_pdo();
        access_ensure_table($pdo);
        $keys = $pdo->query("SELECT id, access_key, label, created_at, expires_at, revoked, last_used_at,
                                    (expires_at > NOW()) AS still_valid
                             FROM access_keys ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('[manage_keys] ' . $e->getMessage());
        $list_error = "Erreur de chargement de la liste des clés.";
    }
}

/** Temps restant lisible ("3 j 4 h", "25 min", "expirée"). */
function keys_remaining_label($expires_at) {
    $diff = strtotime($expires_at) - time();
    if ($diff <= 0) return null;
    if ($diff < 3600)  return ceil($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . ' h ' . floor(($diff % 3600) / 60) . ' min';
    return floor($diff / 86400) . ' j ' . floor(($diff % 86400) / 3600) . ' h';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestion des clés d'accès</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f7f4fb; color: #333; margin: 0; padding: 2rem 1rem; }
        .container { background: #fff; padding: 2rem 2.5rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 1050px; margin: 0 auto; }
        h1 { color: #4a3a86; margin-top: 0; }
        h2 { color: #4a3a86; font-size: 1.1rem; margin: 1.8rem 0 .8rem; }
        button { background-color: #8a7bf4; color: white; padding: 0.55rem 1rem; border: none; border-radius: 6px; font-size: .95rem; cursor: pointer; }
        button:hover { background-color: #7867e6; }
        button.small { padding: .3rem .6rem; font-size: .8rem; }
        button.danger { background-color: #dc3545; }
        button.danger:hover { background-color: #c82333; }
        button.warn { background-color: #d2660b; }
        button.warn:hover { background-color: #b8580a; }
        button.ok { background-color: #0a8a3f; }
        button.ok:hover { background-color: #087a37; }
        input[type=text], input[type=number], input[type=password], select { padding: .5rem; border: 1px solid #ccc; border-radius: 6px; font-size: .95rem; box-sizing: border-box; }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: .8rem 1rem; border-radius: 6px; margin: 1rem 0; }
        .success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: .8rem 1rem; border-radius: 6px; margin: 1rem 0; }
        .logout-form { float: right; }
        .logout-form button { background-color: #6c757d; }
        .gen-form { display: flex; flex-wrap: wrap; gap: .6rem; align-items: flex-end; background: #f4eefb; border: 1px solid #d8cff7; padding: 1rem; border-radius: 10px; }
        .gen-form label { display: block; font-size: .8rem; font-weight: 700; color: #4a3a86; margin-bottom: .25rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: .9rem; }
        th, td { padding: .55rem .6rem; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
        th { background: #f4eefb; color: #4a3a86; }
        tr:hover td { background: #faf8fe; }
        .key-code { font-family: Consolas, Menlo, monospace; font-weight: 700; letter-spacing: 1px; white-space: nowrap; }
        .badge { display: inline-block; padding: .18rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 700; white-space: nowrap; }
        .badge.active  { background: #d4edda; color: #155724; }
        .badge.expired { background: #fff3cd; color: #856404; }
        .badge.revoked { background: #f8d7da; color: #721c24; }
        .row-actions { display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; }
        .row-actions form { display: inline-flex; gap: .3rem; align-items: center; margin: 0; }
        .row-actions input[type=number] { width: 4.2rem; padding: .3rem .4rem; font-size: .8rem; }
        .row-actions select { padding: .3rem .3rem; font-size: .8rem; }
        .muted { color: #888; font-size: .8rem; }
        .login-box { max-width: 420px; margin: 3rem auto; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (admin_is_logged_in()) : ?>

            <form action="" method="post" class="logout-form">
                <?php echo csrf_input(); ?>
                <button type="submit" name="logout">Déconnexion</button>
            </form>

            <h1>🔑 Gestion des clés d'accès</h1>
            <p class="muted">Une clé valide est exigée pour voir et lancer les questionnaires (index.php).
               Les joueurs qui rejoignent une partie par PIN / QR code (play.php) n'en ont pas besoin.
               La validité est revérifiée toutes les ~3 minutes : une clé expirée ou révoquée permet de
               terminer le questionnaire en cours mais bloque le choix d'un nouveau module.</p>

            <?php if ($message): ?>
                <p class="<?php echo $message_type; ?>"><?php echo $message; ?></p>
            <?php endif; ?>

            <h2>Générer une nouvelle clé</h2>
            <form action="" method="post" class="gen-form">
                <?php echo csrf_input(); ?>
                <div style="flex:1; min-width:180px;">
                    <label for="label">Libellé (optionnel — pour qui ?)</label>
                    <input type="text" id="label" name="label" maxlength="190" placeholder="ex. : Lycée Vauban, session juillet" style="width:100%;">
                </div>
                <div>
                    <label for="duration_value">Durée de vie</label>
                    <input type="number" id="duration_value" name="duration_value" min="1" max="3650" value="7" style="width:6rem;">
                </div>
                <div>
                    <label for="duration_unit">Unité</label>
                    <select id="duration_unit" name="duration_unit">
                        <option value="hours">heure(s)</option>
                        <option value="days" selected>jour(s)</option>
                    </select>
                </div>
                <button type="submit" name="generate">Générer la clé</button>
            </form>

            <h2>Clés existantes</h2>
            <?php if ($list_error): ?>
                <p class="error"><?php echo htmlspecialchars($list_error); ?></p>
            <?php elseif (empty($keys)): ?>
                <p class="muted">Aucune clé pour le moment.</p>
            <?php else: ?>
            <table>
                <tr>
                    <th>Clé</th><th>Libellé</th><th>Statut</th><th>Expire le</th><th>Restant</th>
                    <th>Créée le</th><th>Dernier usage</th><th>Actions</th>
                </tr>
                <?php foreach ($keys as $k):
                    $isRevoked = !empty($k['revoked']);
                    $isValid   = !$isRevoked && !empty($k['still_valid']);
                    $remaining = keys_remaining_label($k['expires_at']);
                    $highlight = ($new_key_display !== null && access_format_key($k['access_key']) === $new_key_display);
                ?>
                <tr<?php echo $highlight ? ' style="background:#eaf7ee;"' : ''; ?>>
                    <td class="key-code"><?php echo htmlspecialchars(access_format_key($k['access_key'])); ?></td>
                    <td><?php echo htmlspecialchars($k['label']); ?></td>
                    <td>
                        <?php if ($isRevoked): ?><span class="badge revoked">Révoquée</span>
                        <?php elseif ($isValid): ?><span class="badge active">Active</span>
                        <?php else: ?><span class="badge expired">Expirée</span><?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($k['expires_at']); ?></td>
                    <td><?php echo $remaining !== null ? htmlspecialchars($remaining) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($k['created_at']); ?></td>
                    <td><?php echo $k['last_used_at'] ? htmlspecialchars($k['last_used_at']) : '<span class="muted">jamais</span>'; ?></td>
                    <td>
                        <div class="row-actions">
                            <form action="" method="post">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="key_id" value="<?php echo (int) $k['id']; ?>">
                                <input type="number" name="duration_value" min="1" max="3650" value="7" title="Durée de prolongation">
                                <select name="duration_unit">
                                    <option value="hours">h</option>
                                    <option value="days" selected>j</option>
                                </select>
                                <button type="submit" name="extend" class="small ok" title="Prolonger la validité">+ Prolonger</button>
                            </form>
                            <?php if ($isRevoked): ?>
                            <form action="" method="post">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="key_id" value="<?php echo (int) $k['id']; ?>">
                                <button type="submit" name="reactivate" class="small">Réactiver</button>
                            </form>
                            <?php else: ?>
                            <form action="" method="post" onsubmit="return confirm('Révoquer cette clé ? Les utilisateurs concernés perdront l\'accès sous 3 minutes.');">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="key_id" value="<?php echo (int) $k['id']; ?>">
                                <button type="submit" name="revoke" class="small warn">Révoquer</button>
                            </form>
                            <?php endif; ?>
                            <form action="" method="post" onsubmit="return confirm('Supprimer définitivement cette clé ?');">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="key_id" value="<?php echo (int) $k['id']; ?>">
                                <button type="submit" name="delete" class="small danger">Supprimer</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>

        <?php elseif (admin_login_throttled()['blocked']) : ?>
            <div class="login-box">
                <h1>Accès bloqué</h1>
                <p class="error">Trop de tentatives de connexion. Veuillez réessayer plus tard.</p>
            </div>
        <?php else : ?>
            <div class="login-box">
                <h1>Connexion administrateur</h1>
                <?php if (isset($login_error) && $login_error) : ?><p class="error"><?php echo htmlspecialchars($login_error); ?></p><?php endif; ?>
                <form action="" method="post" style="text-align: left;">
                    <?php echo csrf_input(); ?>
                    <div style="margin-bottom: 1rem;">
                        <label for="identifiant">Identifiant :</label>
                        <input type="text" id="identifiant" name="identifiant" required style="width: 100%;">
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label for="mot_de_passe">Mot de passe :</label>
                        <input type="password" id="mot_de_passe" name="mot_de_passe" required style="width: 100%;">
                    </div>
                    <button type="submit" name="login">Se connecter</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
