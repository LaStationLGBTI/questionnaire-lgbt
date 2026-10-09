<?php
/**
 * i18n.php — Moteur de langues dynamique pour le questionnaire.
 *
 * Fournit :
 *   - i18n_boot()          : DDL + seed + migration one-shot (idempotent).
 *   - i18n_languages()     : langues activées (depuis la table `languages`).
 *   - i18n_enabled_codes() : liste des codes activés.
 *   - i18n_valid_lang()    : validation d'un code de langue.
 *   - i18n_use()           : fixe la langue courante et charge son fichier lang/*.php.
 *   - t()                  : traduction d'une clé + remplacements {placeholder}.
 *   - i18n_dir()           : 'rtl' / 'ltr'.
 *   - i18n_current()       : code de la langue courante.
 *
 * Dégradation gracieuse : toute erreur DB est journalisée (error_log) mais jamais fatale.
 * Compatible PHP 8.2. Aucun framework. Double-include sûr (garde function_exists).
 */

if (!function_exists('t')) {

    /**
     * Valeurs de secours des langues, utilisées si la table `languages`
     * est inaccessible ou vide. fr est la langue de base.
     */
    function i18n_seed_languages(): array
    {
        return [
            ['code' => 'fr', 'label' => 'Français', 'flag_file' => 'france.svg',  'is_rtl' => 0, 'enabled' => 1, 'sort' => 0],
            ['code' => 'en', 'label' => 'English',  'flag_file' => 'uk.svg',      'is_rtl' => 0, 'enabled' => 1, 'sort' => 1],
            ['code' => 'de', 'label' => 'Deutsch',  'flag_file' => 'germany.svg', 'is_rtl' => 0, 'enabled' => 1, 'sort' => 2],
        ];
    }

    /**
     * Crée les tables i18n, seed les langues et migre les anciennes tables _en.
     * Exécuté au plus une fois par requête (static $done). Jamais fatal.
     */
    function i18n_boot(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        // --- a. Table des langues -------------------------------------------------
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS languages (
                code VARCHAR(5) PRIMARY KEY,
                label VARCHAR(50) NOT NULL,
                flag_file VARCHAR(100) NOT NULL DEFAULT '',
                is_rtl TINYINT(1) NOT NULL DEFAULT 0,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                sort INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        } catch (Throwable $e) {
            error_log('i18n_boot: CREATE TABLE languages failed: ' . $e->getMessage());
        }

        // --- b. Traductions des questions ----------------------------------------
        // answer/qtype/level ne sont PAS stockés ici : ils viennent de la ligne
        // française (GSDatabase) via jointure sur fr_id.
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS GSDatabase_i18n (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fr_id INT NOT NULL,
                lang VARCHAR(5) NOT NULL,
                question TEXT,
                rep1 TEXT, rep2 TEXT, rep3 TEXT, rep4 TEXT, rep5 TEXT,
                expliq TEXT,
                UNIQUE KEY uq_frid_lang (fr_id, lang),
                KEY idx_lang (lang)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        } catch (Throwable $e) {
            error_log('i18n_boot: CREATE TABLE GSDatabase_i18n failed: ' . $e->getMessage());
        }

        // --- c. Traductions des titres/descriptions de modules -------------------
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS GSDatabaseT_i18n (
                level VARCHAR(191) NOT NULL,
                lang VARCHAR(5) NOT NULL,
                titre TEXT,
                text TEXT,
                PRIMARY KEY (level, lang)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        } catch (Throwable $e) {
            error_log('i18n_boot: CREATE TABLE GSDatabaseT_i18n failed: ' . $e->getMessage());
        }

        // --- d. Seed des langues si la table est vide ----------------------------
        try {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM languages")->fetchColumn();
            if ($count === 0) {
                $ins = $pdo->prepare("INSERT INTO languages (code, label, flag_file, is_rtl, enabled, sort)
                                      VALUES (:code, :label, :flag_file, :is_rtl, :enabled, :sort)
                                      ON DUPLICATE KEY UPDATE code = code");
                foreach (i18n_seed_languages() as $l) {
                    $ins->execute([
                        ':code'      => $l['code'],
                        ':label'     => $l['label'],
                        ':flag_file' => $l['flag_file'],
                        ':is_rtl'    => $l['is_rtl'],
                        ':enabled'   => $l['enabled'],
                        ':sort'      => $l['sort'],
                    ]);
                }
            }
        } catch (Throwable $e) {
            error_log('i18n_boot: seed languages failed: ' . $e->getMessage());
        }

        // --- e. Migration one-shot des anciennes tables _en ----------------------
        // Questions : GSDatabase_en -> GSDatabase_i18n (lang='en')
        try {
            $hasEn = $pdo->query("SHOW TABLES LIKE 'GSDatabase_en'")->fetchColumn();
            if ($hasEn) {
                $existing = (int) $pdo->query("SELECT COUNT(*) FROM GSDatabase_i18n WHERE lang = 'en'")->fetchColumn();
                if ($existing === 0) {
                    $pdo->exec("INSERT IGNORE INTO GSDatabase_i18n (fr_id, lang, question, rep1, rep2, rep3, rep4, rep5, expliq)
                                SELECT fr_id, 'en', question, rep1, rep2, rep3, rep4, rep5, expliq
                                FROM GSDatabase_en
                                WHERE fr_id IS NOT NULL");
                }
            }
        } catch (Throwable $e) {
            error_log('i18n_boot: migration GSDatabase_en failed: ' . $e->getMessage());
        }

        // Titres : GSDatabaseT_en -> GSDatabaseT_i18n (lang='en')
        try {
            $hasEnT = $pdo->query("SHOW TABLES LIKE 'GSDatabaseT_en'")->fetchColumn();
            if ($hasEnT) {
                $existingT = (int) $pdo->query("SELECT COUNT(*) FROM GSDatabaseT_i18n WHERE lang = 'en'")->fetchColumn();
                if ($existingT === 0) {
                    $pdo->exec("INSERT IGNORE INTO GSDatabaseT_i18n (level, lang, titre, text)
                                SELECT level, 'en', titre, text
                                FROM GSDatabaseT_en");
                }
            }
        } catch (Throwable $e) {
            error_log('i18n_boot: migration GSDatabaseT_en failed: ' . $e->getMessage());
        }
    }

    /**
     * Langues activées, triées par sort puis code. Résultat mis en cache (static).
     * En cas d'erreur DB, renvoie la liste de secours (fr, en, de).
     */
    function i18n_languages(PDO $pdo): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        try {
            $stmt = $pdo->query("SELECT code, label, flag_file, is_rtl, sort
                                 FROM languages
                                 WHERE enabled = 1
                                 ORDER BY sort ASC, code ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows && count($rows) > 0) {
                $cache = array_map(static function ($r) {
                    return [
                        'code'      => (string) $r['code'],
                        'label'     => (string) $r['label'],
                        'flag_file' => (string) $r['flag_file'],
                        'is_rtl'    => (int) $r['is_rtl'],
                        'sort'      => (int) $r['sort'],
                    ];
                }, $rows);
                return $cache;
            }
        } catch (Throwable $e) {
            error_log('i18n_languages: query failed: ' . $e->getMessage());
        }

        // Secours : uniquement les langues activées de la seed.
        $fallback = [];
        foreach (i18n_seed_languages() as $l) {
            if ((int) $l['enabled'] === 1) {
                $fallback[] = [
                    'code'      => $l['code'],
                    'label'     => $l['label'],
                    'flag_file' => $l['flag_file'],
                    'is_rtl'    => (int) $l['is_rtl'],
                    'sort'      => (int) $l['sort'],
                ];
            }
        }
        $cache = $fallback;
        return $cache;
    }

    /**
     * Liste des codes de langues activées.
     */
    function i18n_enabled_codes(PDO $pdo): array
    {
        $codes = [];
        foreach (i18n_languages($pdo) as $l) {
            $codes[] = $l['code'];
        }
        return $codes;
    }

    /**
     * Renvoie $candidate si c'est un code activé, sinon $default.
     * Remplace les anciens tests codés en dur in_array($x, ['de','fr','en']).
     */
    function i18n_valid_lang(PDO $pdo, $candidate, $default = 'fr'): string
    {
        $candidate = is_string($candidate) ? $candidate : '';
        if ($candidate !== '' && in_array($candidate, i18n_enabled_codes($pdo), true)) {
            return $candidate;
        }
        return $default;
    }

    /**
     * Stockage interne de la langue courante + de son tableau de traductions.
     * Accesseur unique pour partager l'état entre i18n_use(), t() et i18n_current().
     */
    function &i18n_state(): array
    {
        static $state = ['lang' => 'fr', 'strings' => null];
        return $state;
    }

    /**
     * Charge un fichier lang/{code}.php et renvoie son tableau, ou [] si absent/invalide.
     */
    function i18n_load_file(string $lang): array
    {
        // Nettoyage défensif du code (a-z, A-Z, 0-9, _-).
        if (!preg_match('/^[A-Za-z0-9_-]{1,10}$/', $lang)) {
            return [];
        }
        $path = __DIR__ . '/lang/' . $lang . '.php';
        if (!is_file($path)) {
            return [];
        }
        try {
            $data = include $path;
        } catch (Throwable $e) {
            error_log('i18n_load_file: include ' . $path . ' failed: ' . $e->getMessage());
            return [];
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Fixe la langue courante et charge son fichier de traduction, fusionné
     * par-dessus fr.php (base de secours). Une clé absente retombe donc en français.
     */
    function i18n_use(string $lang): void
    {
        $state = &i18n_state();

        $base = i18n_load_file('fr');           // base de secours (toutes les clés)
        if ($lang === 'fr') {
            $strings = $base;
        } else {
            $strings = array_merge($base, i18n_load_file($lang));
        }

        $state['lang']    = $lang;
        $state['strings'] = $strings;
    }

    /**
     * Traduction d'une clé. Retombe sur la base française (déjà fusionnée), puis
     * sur la clé elle-même. Remplace chaque {name} par $repl['name'].
     * htmlspecialchars n'est PAS appliqué : à la charge de l'appelant.
     */
    function t(string $key, array $repl = []): string
    {
        $state = &i18n_state();

        // Chargement paresseux si i18n_use() n'a pas encore été appelé.
        if ($state['strings'] === null) {
            i18n_use($state['lang']);
        }

        $value = array_key_exists($key, $state['strings']) ? $state['strings'][$key] : $key;
        $value = (string) $value;

        if (!empty($repl)) {
            foreach ($repl as $k => $v) {
                $value = str_replace('{' . $k . '}', (string) $v, $value);
            }
        }

        return $value;
    }

    /**
     * 'rtl' si la langue est marquée is_rtl, sinon 'ltr'. pdo null -> 'ltr'.
     */
    function i18n_dir(?PDO $pdo, string $lang): string
    {
        if ($pdo === null) {
            return 'ltr';
        }
        foreach (i18n_languages($pdo) as $l) {
            if ($l['code'] === $lang) {
                return ((int) $l['is_rtl'] === 1) ? 'rtl' : 'ltr';
            }
        }
        return 'ltr';
    }

    /**
     * Code de la langue courante (défaut 'fr').
     */
    function i18n_current(): string
    {
        $state = &i18n_state();
        return $state['lang'];
    }

}

// ---------------------------------------------------------------------------
//  Changement de langue en cours de questionnaire (selecteur de drapeaux)
// ---------------------------------------------------------------------------
if (!function_exists('i18n_relocalize_session')) {

    /**
     * Re-traduit le questionnaire DEJA tire en session (QuestionToUse, Rep1..5, expliqs)
     * dans $lang, pour les memes ids francais et dans le meme ordre (IdInUse inchange).
     * answer / qtype / IdInUse / LastQuestion / reponses ne sont PAS touches : aucune
     * reponse n'est ecrite, la progression et le contrat des stats (ids FR) sont conserves.
     * Meme requete (COALESCE/NULLIF, repli FR) que le tirage initial dans index.php.
     * Tout ou rien : en cas d'erreur ou de format inattendu, la session reste intacte.
     */
    function i18n_relocalize_session(PDO $pdo, string $lang): bool
    {
        if (!isset($_SESSION['IdInUse'], $_SESSION['QuestionToUse'])) {
            return false;
        }
        $ids = explode('__', (string) $_SESSION['IdInUse']);
        $n = count($ids);
        $want = [];
        for ($i = 1; $i < $n; $i++) {
            if (ctype_digit((string) $ids[$i])) {
                $want[(int) $ids[$i]] = true;
            }
        }
        if (!$want) {
            return false;
        }
        $idList = array_keys($want);
        $ph = implode(',', array_fill(0, count($idList), '?'));
        try {
            if ($lang === 'fr') {
                $st = $pdo->prepare("SELECT id, question, rep1, rep2, rep3, rep4, rep5, expliq
                                     FROM GSDatabase WHERE id IN ($ph)");
                $st->execute($idList);
            } else {
                $st = $pdo->prepare("SELECT f.id,
                        COALESCE(NULLIF(i.question, ''), f.question) AS question,
                        COALESCE(NULLIF(i.rep1, ''), f.rep1) AS rep1, COALESCE(NULLIF(i.rep2, ''), f.rep2) AS rep2,
                        COALESCE(NULLIF(i.rep3, ''), f.rep3) AS rep3, COALESCE(NULLIF(i.rep4, ''), f.rep4) AS rep4,
                        COALESCE(NULLIF(i.rep5, ''), f.rep5) AS rep5, COALESCE(NULLIF(i.expliq, ''), f.expliq) AS expliq
                    FROM GSDatabase f
                    LEFT JOIN GSDatabase_i18n i ON i.fr_id = f.id AND i.lang = ?
                    WHERE f.id IN ($ph)");
                $st->execute(array_merge([$lang], $idList));
            }
            $rows = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rows[(int) $r['id']] = $r;
            }
        } catch (Throwable $e) {
            error_log('i18n_relocalize_session: ' . $e->getMessage());
            return false;
        }
        $map = ['QuestionToUse' => 'question', 'Rep1' => 'rep1', 'Rep2' => 'rep2', 'Rep3' => 'rep3',
                'Rep4' => 'rep4', 'Rep5' => 'rep5', 'expliqs' => 'expliq'];
        $new = [];
        foreach ($map as $sk => $col) {
            if (!isset($_SESSION[$sk])) {
                if ($sk === 'expliqs') { continue; } // anciennes sessions sans expliqs
                return false;
            }
            $cur = explode('__', (string) $_SESSION[$sk]);
            if (count($cur) !== $n) {
                return false; // format inattendu : on ne touche a rien
            }
            for ($i = 1; $i < $n; $i++) {
                $id = (int) $ids[$i];
                if (!isset($rows[$id])) {
                    continue; // question supprimee entre-temps : texte d'origine conserve
                }
                $val = (string) $rows[$id][$col];
                if (strpos($val, '__') !== false) {
                    continue; // casserait le format __ : texte d'origine conserve
                }
                $cur[$i] = $val;
            }
            $new[$sk] = implode('__', $cur);
        }
        foreach ($new as $sk => $v) {
            $_SESSION[$sk] = $v;
        }
        return true;
    }

    /**
     * Vrai si la question $n vient d'etre repondue (popup « bonne reponse » + bouton
     * Continuer encore a l'ecran) : le serveur a deja avance (LastQuestion = $n + 1, ou
     * finish apres la derniere). Permet de reafficher CETTE question, en lecture seule,
     * apres un rechargement de changement de langue. Solo uniquement, qcm/echelle seulement.
     */
    function i18n_review_ok($n): bool
    {
        $n = (int) $n;
        if ($n < 1 || !empty($_SESSION['game_mode']) || isset($_SESSION['acc'])
            || !isset($_SESSION['start'], $_SESSION['LastQuestion'], $_SESSION['TotalQuestions'],
                $_SESSION['QuestionToUse'], $_SESSION['qtype'])) {
            return false;
        }
        if ($n !== (int) $_SESSION['LastQuestion'] - 1 || $n > (int) $_SESSION['TotalQuestions']) {
            return false;
        }
        $types = explode('__', (string) $_SESSION['qtype']);
        $qs = explode('__', (string) $_SESSION['QuestionToUse']);
        return isset($types[$n], $qs[$n]) && ($types[$n] === 'qcm' || $types[$n] === 'echelle');
    }

    /**
     * Selecteur de langue compact (drapeaux des langues activees). Chaque drapeau poste
     * « language » vers index.php (mecanisme existant). uq_review / uq_choice sont remplis
     * par uqLangSubmit() (index.php) quand une reponse vient d'etre donnee, pour reafficher
     * la meme question avec sa reponse apres rechargement.
     */
    function i18n_switcher_html(array $langs, string $current): string
    {
        if (count($langs) < 2) {
            return '';
        }
        $h = '<style>'
            . '.lang-switch{position:fixed;top:12px;right:12px;z-index:9990;display:flex;gap:6px;padding:6px;'
            . 'background:#fff;border:2px solid #8a7bf4;border-radius:30px;box-shadow:0 4px 16px rgba(74,58,134,.28);}'
            . '.lang-switch form{margin:0;display:inline;}'
            . '.lang-switch__btn{display:flex;align-items:center;gap:7px;height:40px;padding:0 14px 0 5px;margin:0;'
            . 'border:2px solid transparent;border-radius:24px;background:#f4eefb;color:#4a3a86;cursor:pointer;'
            . 'font:800 15px/1 sans-serif;letter-spacing:.5px;transition:background .15s,border-color .15s;}'
            . '.lang-switch__btn img{width:30px;height:30px;border-radius:50%;object-fit:cover;display:block;box-shadow:0 0 0 1px rgba(0,0,0,.15);}'
            . '.lang-switch__btn:hover{border-color:#8a7bf4;background:#ebe4fb;}'
            . '.lang-switch__btn.is-current{background:#8a7bf4;color:#fff;cursor:default;}'
            . '@media (max-width:480px){.lang-switch{top:8px;right:8px;}.lang-switch__btn{height:34px;padding:0 10px 0 4px;font-size:13px;}'
            . '.lang-switch__btn img{width:24px;height:24px;}}'
            . '</style>';
        $h .= '<div class="lang-switch" role="group" aria-label="' . htmlspecialchars(t('lang_switch_label')) . '">';
        foreach ($langs as $L) {
            $code = (string) $L['code'];
            $label = htmlspecialchars((string) $L['label']);
            $cur = ($code === $current);
            $h .= '<form method="POST" action="index.php" onsubmit="return (typeof uqLangSubmit === \'function\') ? uqLangSubmit(this) : true;">'
                . '<input type="hidden" name="language" value="' . htmlspecialchars($code) . '">'
                . '<input type="hidden" name="uq_review" value="">'
                . '<input type="hidden" name="uq_choice" value="">'
                . '<button type="submit" class="lang-switch__btn' . ($cur ? ' is-current' : '') . '" title="' . $label . '"'
                . ($cur ? ' aria-current="true" disabled' : '') . '>'
                . '<img src="images/' . htmlspecialchars((string) $L['flag_file']) . '" alt="">'
                . '<span>' . htmlspecialchars(strtoupper($code)) . '</span>'
                . '</button></form>';
        }
        return $h . '</div>';
    }
}
