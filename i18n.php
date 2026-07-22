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
