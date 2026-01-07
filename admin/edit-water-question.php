<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/water-questions.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';

$questionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$questionId) {
    redirect('/admin/manage-water-questions.php');
    exit;
}

// Get question
$stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "water_questions WHERE question_id = ?");
$stmt->execute([$questionId]);
$question = $stmt->fetch();

if (!$question) {
    redirect('/admin/manage-water-questions.php');
    exit;
}

// Get question options
$stmt = $db->prepare("
    SELECT * FROM " . TABLE_PREFIX . "water_question_options
    WHERE question_id = ?
    ORDER BY display_order, option_id
");
$stmt->execute([$questionId]);
$options = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_question') {
        $questionText = $_POST['question_text'] ?? '';
        $questionType = $_POST['question_type'] ?? 'dropdown';
        $pageTag = $_POST['page_tag'] ?? 'water_info';
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $dependsOnQuestionId = !empty($_POST['depends_on_question_id']) ? (int)$_POST['depends_on_question_id'] : null;
        $dependsOnAnswerValue = $_POST['depends_on_answer_value'] ?? null;
        $helpText = $_POST['help_text'] ?? null;
        $termsLink = $_POST['terms_link'] ?? null;
        
        if (empty($questionText)) {
            $error = 'Question text is required.';
        } else {
            $db->beginTransaction();
            try {
                // Update question
                $stmt = $db->prepare("
                    UPDATE " . TABLE_PREFIX . "water_questions
                    SET question_text = ?, question_type = ?, page_tag = ?, display_order = ?, 
                        is_required = ?, is_active = ?, depends_on_question_id = ?, 
                        depends_on_answer_value = ?, help_text = ?, terms_link = ?
                    WHERE question_id = ?
                ");
                $stmt->execute([
                    $questionText, $questionType, $pageTag, $displayOrder, $isRequired, $isActive,
                    $dependsOnQuestionId, $dependsOnAnswerValue, $helpText, $termsLink, $questionId
                ]);
                
                // Delete existing options
                $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "water_question_options WHERE question_id = ?");
                $stmt->execute([$questionId]);
                
                // Add new options if provided
                if (isset($_POST['options']) && is_array($_POST['options'])) {
                    foreach ($_POST['options'] as $index => $option) {
                        if (!empty($option['value']) && !empty($option['text'])) {
                            $stmt = $db->prepare("
                                INSERT INTO " . TABLE_PREFIX . "water_question_options
                                (question_id, option_value, option_text, display_order)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([$questionId, $option['value'], $option['text'], $index + 1]);
                        }
                    }
                }
                
                $db->commit();
                $message = 'Question updated successfully.';
                
                // Reload question and options
                $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "water_questions WHERE question_id = ?");
                $stmt->execute([$questionId]);
                $question = $stmt->fetch();
                
                $stmt = $db->prepare("
                    SELECT * FROM " . TABLE_PREFIX . "water_question_options
                    WHERE question_id = ?
                    ORDER BY display_order, option_id
                ");
                $stmt->execute([$questionId]);
                $options = $stmt->fetchAll();
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to update question: ' . $e->getMessage();
            }
        }
    }
}

// Get all questions for dependency dropdown
$stmt = $db->query("
    SELECT question_id, question_text 
    FROM " . TABLE_PREFIX . "water_questions
    WHERE question_id != ? AND page_tag = ?
    ORDER BY display_order, question_id
");
$stmt->execute([$questionId, $question['page_tag']]);
$allQuestions = $stmt->fetchAll();

$pageTitle = 'Edit Water Question';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Edit Water Question</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="question-form">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_question">
                
                <div class="form-group">
                    <label for="question_text">Question Text: <span class="required">*</span></label>
                    <textarea id="question_text" name="question_text" rows="2" required><?= h($question['question_text']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="question_type">Question Type: <span class="required">*</span></label>
                    <select id="question_type" name="question_type" required>
                        <option value="dropdown" <?= $question['question_type'] === 'dropdown' ? 'selected' : '' ?>>Dropdown</option>
                        <option value="radio" <?= $question['question_type'] === 'radio' ? 'selected' : '' ?>>Radio Button</option>
                        <option value="checkbox" <?= $question['question_type'] === 'checkbox' ? 'selected' : '' ?>>Checkbox</option>
                        <option value="text" <?= $question['question_type'] === 'text' ? 'selected' : '' ?>>Text Input</option>
                        <option value="textarea" <?= $question['question_type'] === 'textarea' ? 'selected' : '' ?>>Textarea</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="page_tag">Page Tag:</label>
                    <input type="text" id="page_tag" name="page_tag" value="<?= h($question['page_tag']) ?>">
                    <small>Used to group questions for different pages</small>
                </div>
                
                <div class="form-group">
                    <label for="display_order">Display Order:</label>
                    <input type="number" id="display_order" name="display_order" value="<?= $question['display_order'] ?>">
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_required" value="1" <?= $question['is_required'] ? 'checked' : '' ?>>
                        Required
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" value="1" <?= $question['is_active'] ? 'checked' : '' ?>>
                        Active
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="depends_on_question_id">Depends On Question:</label>
                    <select id="depends_on_question_id" name="depends_on_question_id">
                        <option value="">None</option>
                        <?php foreach ($allQuestions as $q): ?>
                            <option value="<?= $q['question_id'] ?>" <?= $question['depends_on_question_id'] == $q['question_id'] ? 'selected' : '' ?>>
                                Q<?= $q['question_id'] ?>: <?= h(substr($q['question_text'], 0, 50)) ?><?= strlen($q['question_text']) > 50 ? '...' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Question ID this question depends on</small>
                </div>
                
                <div class="form-group">
                    <label for="depends_on_answer_value">Depends On Answer Value:</label>
                    <input type="text" id="depends_on_answer_value" name="depends_on_answer_value" value="<?= h($question['depends_on_answer_value'] ?? '') ?>" placeholder="Answer value that triggers this question">
                    <small>Show this question only when dependency has this value</small>
                </div>
                
                <div class="form-group">
                    <label for="help_text">Help Text:</label>
                    <textarea id="help_text" name="help_text" rows="2"><?= h($question['help_text'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="terms_link">Terms Link:</label>
                    <input type="text" id="terms_link" name="terms_link" value="<?= h($question['terms_link'] ?? '') ?>" placeholder="/water-data-terms.php">
                    <small>Link to terms page (for checkbox questions)</small>
                </div>
                
                <div id="options-container">
                    <h3>Options (for dropdown/radio/checkbox)</h3>
                    <div id="options-list">
                        <?php foreach ($options as $index => $option): ?>
                            <div class="option-item">
                                <input type="text" name="options[<?= $index ?>][value]" value="<?= h($option['option_value']) ?>" placeholder="Value" required>
                                <input type="text" name="options[<?= $index ?>][text]" value="<?= h($option['option_text']) ?>" placeholder="Display Text" required>
                                <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="addOption()">Add Option</button>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update Question</button>
                    <a href="<?= baseUrl('/admin/manage-water-questions.php') ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let optionCount = <?= count($options) ?>;

function addOption() {
    const container = document.getElementById('options-list');
    const div = document.createElement('div');
    div.className = 'option-item';
    div.innerHTML = `
        <input type="text" name="options[${optionCount}][value]" placeholder="Value" required>
        <input type="text" name="options[${optionCount}][text]" placeholder="Display Text" required>
        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">Remove</button>
    `;
    container.appendChild(div);
    optionCount++;
}
</script>

<style>
.option-item {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    align-items: center;
}

.option-item input {
    flex: 1;
}
</style>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>


