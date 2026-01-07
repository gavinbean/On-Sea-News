<?php
/**
 * Water Questions Functions
 * Dynamic question/answer system for water information
 */

require_once __DIR__ . '/functions.php';

/**
 * Get all active questions for a page tag
 */
function getWaterQuestions($pageTag = 'water_info', $userId = null) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT q.*
        FROM " . TABLE_PREFIX . "water_questions q
        WHERE q.page_tag = ? AND q.is_active = 1
        ORDER BY q.display_order, q.question_id
    ");
    $stmt->execute([$pageTag]);
    $questions = $stmt->fetchAll();
    
    // Get options for each question
    foreach ($questions as &$question) {
        $stmt = $db->prepare("
            SELECT option_id, option_value, option_text, display_order
            FROM " . TABLE_PREFIX . "water_question_options
            WHERE question_id = ?
            ORDER BY display_order, option_id
        ");
        $stmt->execute([$question['question_id']]);
        $question['options'] = $stmt->fetchAll();
        
        // Get user's existing response if user_id provided
        if ($userId) {
            $stmt = $db->prepare("
                SELECT response_value, response_text
                FROM " . TABLE_PREFIX . "water_user_responses
                WHERE user_id = ? AND question_id = ?
            ");
            $stmt->execute([$userId, $question['question_id']]);
            $response = $stmt->fetch();
            $question['user_response'] = $response;
        }
    }
    
    return $questions;
}

/**
 * Get questions with dependency resolution
 * Returns questions that should be visible based on current answers
 */
function getVisibleWaterQuestions($pageTag = 'water_info', $answers = [], $userId = null) {
    $allQuestions = getWaterQuestions($pageTag, $userId);
    $visibleQuestions = [];
    
    foreach ($allQuestions as $question) {
        $shouldShow = true;
        
        // Check if question has dependencies
        if ($question['depends_on_question_id']) {
            $dependsOnId = $question['depends_on_question_id'];
            $dependsOnValue = $question['depends_on_answer_value'];
            
            // Check if dependency is met
            if (isset($answers[$dependsOnId])) {
                $answerValue = $answers[$dependsOnId];
                // For checkboxes, check if value is in array
                if (is_array($answerValue)) {
                    $shouldShow = in_array($dependsOnValue, $answerValue);
                } else {
                    $shouldShow = ($answerValue == $dependsOnValue);
                }
            } else {
                // Dependency not answered, don't show
                $shouldShow = false;
            }
        }
        
        if ($shouldShow) {
            $visibleQuestions[] = $question;
        }
    }
    
    return $visibleQuestions;
}

/**
 * Save user responses to water questions
 */
function saveWaterResponses($userId, $responses) {
    $db = getDB();
    $db->beginTransaction();
    
    try {
        foreach ($responses as $questionId => $responseValue) {
            // Handle checkbox arrays
            if (is_array($responseValue)) {
                $responseValue = implode(',', $responseValue);
            }
            
            // Delete existing response
            $stmt = $db->prepare("
                DELETE FROM " . TABLE_PREFIX . "water_user_responses
                WHERE user_id = ? AND question_id = ?
            ");
            $stmt->execute([$userId, $questionId]);
            
            // Insert new response
            if (!empty($responseValue)) {
                $stmt = $db->prepare("
                    INSERT INTO " . TABLE_PREFIX . "water_user_responses
                    (user_id, question_id, response_value, response_text)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        response_value = VALUES(response_value),
                        response_text = VALUES(response_text),
                        updated_at = NOW()
                ");
                $stmt->execute([$userId, $questionId, $responseValue, $responseValue]);
            }
        }
        
        $db->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error saving water responses: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to save responses.'];
    }
}

/**
 * Get user's water responses
 */
function getUserWaterResponses($userId, $pageTag = 'water_info') {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT r.question_id, r.response_value, r.response_text, r.updated_at
        FROM " . TABLE_PREFIX . "water_user_responses r
        JOIN " . TABLE_PREFIX . "water_questions q ON r.question_id = q.question_id
        WHERE r.user_id = ? AND q.page_tag = ?
    ");
    $stmt->execute([$userId, $pageTag]);
    $responses = $stmt->fetchAll();
    
    $result = [];
    foreach ($responses as $response) {
        $result[$response['question_id']] = $response;
    }
    
    return $result;
}

/**
 * Get answers for dependency checking
 */
function getAnswersForDependencies($userId, $pageTag = 'water_info') {
    $responses = getUserWaterResponses($userId, $pageTag);
    $answers = [];
    
    foreach ($responses as $questionId => $response) {
        $value = $response['response_value'];
        // Handle comma-separated checkbox values
        if (strpos($value, ',') !== false) {
            $answers[$questionId] = explode(',', $value);
        } else {
            $answers[$questionId] = $value;
        }
    }
    
    return $answers;
}

