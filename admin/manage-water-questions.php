<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/water-questions.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';

// Handle question creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_question') {
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
            $stmt = $db->prepare("
                INSERT INTO " . TABLE_PREFIX . "water_questions
                (question_text, question_type, page_tag, display_order, is_required, is_active, 
                 depends_on_question_id, depends_on_answer_value, help_text, terms_link)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $questionText, $questionType, $pageTag, $displayOrder, $isRequired, $isActive,
                $dependsOnQuestionId, $dependsOnAnswerValue, $helpText, $termsLink
            ]);
            $questionId = $db->lastInsertId();
            
            // Add options if provided
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
            
            $message = 'Question created successfully.';
        }
    } elseif ($_POST['action'] === 'update_question') {
        $questionId = (int)($_POST['question_id'] ?? 0);
        $questionText = $_POST['question_text'] ?? '';
        $questionType = $_POST['question_type'] ?? 'dropdown';
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $dependsOnQuestionId = !empty($_POST['depends_on_question_id']) ? (int)$_POST['depends_on_question_id'] : null;
        $dependsOnAnswerValue = $_POST['depends_on_answer_value'] ?? null;
        $helpText = $_POST['help_text'] ?? null;
        $termsLink = $_POST['terms_link'] ?? null;
        
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "water_questions
            SET question_text = ?, question_type = ?, display_order = ?, is_required = ?, is_active = ?,
                depends_on_question_id = ?, depends_on_answer_value = ?, help_text = ?, terms_link = ?
            WHERE question_id = ?
        ");
        $stmt->execute([
            $questionText, $questionType, $displayOrder, $isRequired, $isActive,
            $dependsOnQuestionId, $dependsOnAnswerValue, $helpText, $termsLink, $questionId
        ]);
        $message = 'Question updated successfully.';
    } elseif ($_POST['action'] === 'toggle_active') {
        $questionId = (int)($_POST['question_id'] ?? 0);
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "water_questions
            SET is_active = NOT is_active
            WHERE question_id = ?
        ");
        $stmt->execute([$questionId]);
        $message = 'Question status updated.';
    }
}

// Get all questions
$questions = getWaterQuestions('water_info');

$pageTitle = 'Manage Water Questions';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Manage Water Questions</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="questions-list">
            <h2>Existing Questions</h2>
            <?php if (empty($questions)): ?>
                <p>No questions yet. Create your first question below.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Question</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Status</th>
                            <th>Dependency</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $question): ?>
                            <tr>
                                <td><?= $question['display_order'] ?></td>
                                <td><?= h(substr($question['question_text'], 0, 60)) ?><?= strlen($question['question_text']) > 60 ? '...' : '' ?></td>
                                <td><?= h($question['question_type']) ?></td>
                                <td><?= $question['is_required'] ? 'Yes' : 'No' ?></td>
                                <td>
                                    <span class="status-badge <?= $question['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $question['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($question['depends_on_question_id']): ?>
                                        Q<?= $question['depends_on_question_id'] ?> = <?= h($question['depends_on_answer_value']) ?>
                                    <?php else: ?>
                                        None
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="question_id" value="<?= $question['question_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary">
                                            <?= $question['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                    <a href="<?= baseUrl('/admin/edit-water-question.php?id=' . $question['question_id']) ?>" class="btn btn-sm btn-primary">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="question-form">
            <h2>Create New Question</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_question">
                
                <div class="form-group">
                    <label for="question_text">Question Text: <span class="required">*</span></label>
                    <textarea id="question_text" name="question_text" rows="2" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="question_type">Question Type: <span class="required">*</span></label>
                    <select id="question_type" name="question_type" required>
                        <option value="dropdown">Dropdown</option>
                        <option value="radio">Radio Button</option>
                        <option value="checkbox">Checkbox</option>
                        <option value="text">Text Input</option>
                        <option value="textarea">Textarea</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="page_tag">Page Tag:</label>
                    <input type="text" id="page_tag" name="page_tag" value="water_info">
                    <small>Used to group questions for different pages</small>
                </div>
                
                <div class="form-group">
                    <label for="display_order">Display Order:</label>
                    <input type="number" id="display_order" name="display_order" value="0">
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_required" value="1">
                        Required
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Active
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="depends_on_question_id">Depends On Question ID:</label>
                    <input type="number" id="depends_on_question_id" name="depends_on_question_id" placeholder="Leave empty if no dependency">
                    <small>Question ID this question depends on</small>
                </div>
                
                <div class="form-group">
                    <label for="depends_on_answer_value">Depends On Answer Value:</label>
                    <input type="text" id="depends_on_answer_value" name="depends_on_answer_value" placeholder="Answer value that triggers this question">
                    <small>Show this question only when dependency has this value</small>
                </div>
                
                <div class="form-group">
                    <label for="help_text">Help Text:</label>
                    <textarea id="help_text" name="help_text" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="terms_link">Terms Link:</label>
                    <input type="text" id="terms_link" name="terms_link" placeholder="/water-data-terms.php">
                    <small>Link to terms page (for checkbox questions)</small>
                </div>
                
                <div id="options-container">
                    <h3>Options (for dropdown/radio/checkbox)</h3>
                    <div id="options-list"></div>
                    <button type="button" class="btn btn-secondary" onclick="addOption()">Add Option</button>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Create Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let optionCount = 0;

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
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
}

.status-badge.active {
    background-color: #d4edda;
    color: #155724;
}

.status-badge.inactive {
    background-color: #f8d7da;
    color: #721c24;
}

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

