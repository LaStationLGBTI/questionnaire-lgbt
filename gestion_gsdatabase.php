<?php
// Подключаем конфигурацию и запускаем сессию
require_once 'conf.php';
session_start();

// --- Секция 1: Логика входа и выхода (аналогично вашему файлу) ---

// Инициализируем счетчик попыток входа
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

// Обработка выхода из системы
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: gestion_gsdatabase.php');
    exit();
}

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_SESSION['login_attempts'] < 3) {
        $login = $_POST['identifiant'];
        $pass = $_POST['mot_de_passe'];
        try {
            $pdo_login = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
            $pdo_login->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo_login->prepare("SELECT passconn FROM stationl1 WHERE loginconn = ?");
            $stmt->execute([$login]);
            $user = $stmt->fetch();
            if ($user && $pass === $user['passconn']) {
                $_SESSION['is_logged_in'] = true;
                $_SESSION['login_attempts'] = 0;
            } else {
                $_SESSION['login_attempts']++;
                $login_error = "Identifiant ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $login_error = "Erreur de connexion : " . $e->getMessage();
        }
    }
}

// --- Секция 2: Обработка данных (создание, обновление, удаление) ---
$message = ''; // Для сообщений пользователю (успех, ошибка)

if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    try {
        $pdo = new PDO("mysql:host=$DB_HOSTNAME;dbname=$DB_NAME;charset=utf8", $DB_USERNAME, $DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Обработка создания нового вопроса
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_question'])) {
            $answer = ($_POST['qtype'] === 'qcm') ? $_POST['answer'] : 0;
            $sql = "INSERT INTO GSDatabase (level, question, rep1, rep2, rep3, rep4, rep5, answer, qtype, expliq) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['level'], $_POST['question'], $_POST['rep1'], $_POST['rep2'],
                $_POST['rep3'], $_POST['rep4'], $_POST['rep5'], $answer,
                $_POST['qtype'], $_POST['expliq']
            ]);
            $message = "<div class='message success'>Question ajoutée avec succès !</div>";
        }

        // Обработка обновления вопроса
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_question'])) {
            $answer = ($_POST['qtype'] === 'qcm') ? $_POST['answer'] : 0;
            $sql = "UPDATE GSDatabase SET level=?, question=?, rep1=?, rep2=?, rep3=?, rep4=?, rep5=?, answer=?, qtype=?, expliq=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['level'], $_POST['question'], $_POST['rep1'], $_POST['rep2'],
                $_POST['rep3'], $_POST['rep4'], $_POST['rep5'], $answer,
                $_POST['qtype'], $_POST['expliq'], $_POST['id']
            ]);
            $message = "<div class='message success'>Question mise à jour avec succès !</div>";
        }

        // Обработка удаления вопроса
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
            $stmt = $pdo->prepare("DELETE FROM GSDatabase WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            header('Location: gestion_gsdatabase.php?deleted=true');
            exit();
        }

    } catch (PDOException $e) {
        $message = "<div class='error'>Erreur de base de données : " . $e->getMessage() . "</div>";
    }
}

// Сообщение об успешном удалении
if (isset($_GET['deleted'])) {
    $message = "<div class='message success'>Question supprimée avec succès !</div>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion GSDatabase</title>
    <style>
        /* Стили скопированы из вашего файла и немного дополнены */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f4f9; color: #333; margin: 0; padding: 20px; }
        .container { background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 900px; margin: auto; }
        .login-container { max-width: 500px; margin-top: 10vh; }
        h1, h2 { color: #5a5a5a; text-align: center; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input[type="text"], input[type="password"], input[type="number"], textarea, select { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-size: 1rem; }
        textarea { resize: vertical; min-height: 100px; }
        button, .button-link { background-color: #007bff; color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s; width: 100%; text-decoration: none; display: inline-block; text-align: center; box-sizing: border-box; }
        button:hover, .button-link:hover { background-color: #0056b3; }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; border-radius: 5px; margin-top: 1rem; text-align: center; }
        .message.success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; padding: 1rem; border-radius: 5px; text-align: center; margin-bottom: 1rem; }
        .logout-form { position: absolute; top: 20px; right: 20px; }
        .logout-form button { background-color: #6c757d; width: auto; }
        .logout-form button:hover { background-color: #5a6268; }
        .action-menu { text-align: center; margin: 2rem 0; }
        .action-menu .button-link { width: auto; margin: 0 10px; }
        .form-actions { display: flex; gap: 1rem; justify-content: space-between; margin-top: 2rem; }
        .form-actions button { width: auto; flex-grow: 1; }
        .form-actions button[name="delete_question"] { background-color: #dc3545; }
        .form-actions button[name="delete_question"]:hover { background-color: #c82333; }
        .hidden { display: none; }
        .radio-group label { display: inline-block; margin-right: 20px; font-weight: normal; }
        .back-link { display: inline-block; margin-bottom: 2rem; color: #007bff; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) : ?>
    
    <div class="container">
        <form action="" method="post" class="logout-form">
            <button type="submit" name="logout">Déconnexion</button>
        </form>
        
        <h1>Gestion de la GSDatabase</h1>
        <?php echo $message; // Отображение сообщений ?>

        <?php
        $action = $_GET['action'] ?? 'menu';
        $id = $_REQUEST['id'] ?? null;
        
        // --- Главное меню ---
        if ($action === 'menu') :
        ?>
            <div class="action-menu">
                <h2>Que souhaitez-vous faire ?</h2>
                <a href="?action=create" class="button-link">Créer une nouvelle question</a>
                <a href="?action=edit" class="button-link">Modifier ou Supprimer une question</a>
            </div>

        <?php 
        // --- Форма для ввода ID для редактирования ---
        elseif ($action === 'edit' && !$id) : 
        ?>
            <a href="gestion_gsdatabase.php" class="back-link">&larr; Retour au menu</a>
            <h2>Modifier une question</h2>
            <form action="" method="GET">
                <input type="hidden" name="action" value="edit">
                <div class="form-group">
                    <label for="id">Entrez l'ID de la question à modifier :</label>
                    <input type="number" id="id" name="id" required>
                </div>
                <button type="submit">Rechercher et Modifier</button>
            </form>

        <?php 
        // --- Форма для создания или редактирования вопроса ---
        elseif ($action === 'create' || ($action === 'edit' && $id)) :
            
            $question_data = null;
            $is_edit_mode = false;
            if ($action === 'edit' && $id) {
                $is_edit_mode = true;
                $stmt = $pdo->prepare("SELECT * FROM GSDatabase WHERE id = ?");
                $stmt->execute([$id]);
                $question_data = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$question_data) {
                    echo "<div class='error'>Question avec l'ID $id non trouvée.</div>";
                    echo '<a href="?action=edit" class="back-link">Essayer un autre ID</a>';
                    exit; // Останавливаем выполнение, если ID не найден
                }
            }
        ?>
            <a href="gestion_gsdatabase.php" class="back-link">&larr; Retour au menu</a>
            <h2><?php echo $is_edit_mode ? 'Modifier la question' : 'Créer une nouvelle question'; ?></h2>

            <form action="gestion_gsdatabase.php" method="POST" id="question-form">
                <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($question_data['id']); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="level">Module (level) :</label>
                    <input type="text" id="level" name="level" value="<?php echo htmlspecialchars($question_data['level'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="question">Question :</label>
                    <textarea id="question" name="question" required><?php echo htmlspecialchars($question_data['question'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Type de question (qtype) :</label>
                    <div class="radio-group">
                        <label><input type="radio" name="qtype" value="qcm" <?php echo ($question_data['qtype'] ?? 'qcm') === 'qcm' ? 'checked' : ''; ?>> QCM</label>
                        <label><input type="radio" name="qtype" value="echelle" <?php echo ($question_data['qtype'] ?? '') === 'echelle' ? 'checked' : ''; ?>> Échelle</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="rep1">Réponse 1 :</label>
                    <input type="text" id="rep1" name="rep1" value="<?php echo htmlspecialchars($question_data['rep1'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="rep2">Réponse 2 :</label>
                    <input type="text" id="rep2" name="rep2" value="<?php echo htmlspecialchars($question_data['rep2'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="rep3">Réponse 3 :</label>
                    <input type="text" id="rep3" name="rep3" value="<?php echo htmlspecialchars($question_data['rep3'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="rep4">Réponse 4 :</label>
                    <input type="text" id="rep4" name="rep4" value="<?php echo htmlspecialchars($question_data['rep4'] ?? ''); ?>" required>
                </div>
                <div id="rep5-group" class="form-group hidden">
                    <label for="rep5">Réponse 5 :</label>
                    <input type="text" id="rep5" name="rep5" value="<?php echo htmlspecialchars($question_data['rep5'] ?? ''); ?>">
                </div>

                <div id="answer-group" class="form-group hidden">
                    <label for="answer">Numéro de la bonne réponse (pour QCM) :</label>
                    <input type="number" id="answer" name="answer" min="1" max="4" value="<?php echo htmlspecialchars($question_data['answer'] ?? '1'); ?>">
                </div>

                <div class="form-group">
                    <label for="expliq">Explication de la réponse :</label>
                    <textarea id="expliq" name="expliq"><?php echo htmlspecialchars($question_data['expliq'] ?? ''); ?></textarea>
                </div>

                <div class="form-actions">
                    <?php if ($is_edit_mode): ?>
                        <button type="submit" name="update_question">Mettre à jour</button>
                        <button type="submit" name="delete_question" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette question ? Cette action est irréversible.');">Supprimer la question</button>
                    <?php else: ?>
                        <button type="submit" name="create_question">Créer la question</button>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>

<?php elseif ($_SESSION['login_attempts'] >= 3) : ?>
    <div class="container login-container">
        <h1>Accès Bloqué</h1>
        <p class="error">Votre accès est bloqué après 3 tentatives infructueuses.</p>
    </div>
<?php else : ?>
    <div class="container login-container">
        <h1>Accès Administrateur</h1>
        <?php if (isset($login_error)) : ?>
            <p class="error"><?php echo $login_error; ?></p>
            <p style="text-align:center;">Tentative <?php echo $_SESSION['login_attempts']; ?> sur 3.</p>
        <?php endif; ?>
        <form action="" method="post">
            <div class="form-group">
                <label for="identifiant">Identifiant :</label>
                <input type="text" id="identifiant" name="identifiant" required>
            </div>
            <div class="form-group">
                <label for="mot_de_passe">Mot de passe :</label>
                <input type="password" id="mot_de_passe" name="mot_de_passe" required>
            </div>
            <button type="submit" name="login">Connexion</button>
        </form>
    </div>
<?php endif; ?>

<script>
// --- Секция 3: JavaScript для динамической формы ---
function handleQTypeChange() {
    const qtype = document.querySelector('input[name="qtype"]:checked').value;
    const rep5Group = document.getElementById('rep5-group');
    const answerGroup = document.getElementById('answer-group');

    if (qtype === 'echelle') {
        rep5Group.classList.remove('hidden');
        answerGroup.classList.add('hidden');
    } else { // qcm
        rep5Group.classList.add('hidden');
        answerGroup.classList.remove('hidden');
    }
}
document.addEventListener('DOMContentLoaded', () => {
    const qtypeRadios = document.querySelectorAll('input[name="qtype"]');
    if (qtypeRadios.length > 0) {
        qtypeRadios.forEach(radio => radio.addEventListener('change', handleQTypeChange));
        // Вызываем функцию при загрузке, чтобы установить правильное состояние формы
        handleQTypeChange();
    }
});
</script>

</body>
</html>
