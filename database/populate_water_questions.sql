-- Populate initial water questions and options

-- Question 1: Do you have tanks on your property?
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`) 
VALUES ('Do you have tanks on your property?', 'dropdown', 'water_info', 1, 1, 1);

SET @q1_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q1_id, 'yes', 'Yes', 1),
(@q1_id, 'no', 'No', 2);

-- Question 1a: How big are your tanks? (depends on Q1 = Yes)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`, `depends_on_question_id`, `depends_on_answer_value`) 
VALUES ('How big are your tanks?', 'dropdown', 'water_info', 2, 1, 0, @q1_id, 'yes');

SET @q1a_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q1a_id, '2500', '2500 Litres', 1),
(@q1a_id, '5000', '5000 Litres', 2),
(@q1a_id, '10000', '10 000 Litres', 3),
(@q1a_id, '15000', '15 000 Litres', 4),
(@q1a_id, '20000', '20 000 Litres Plus', 5);

-- Question 1b: Do you get water delivered to top up your tanks? (depends on Q1 = Yes)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`, `depends_on_question_id`, `depends_on_answer_value`) 
VALUES ('Do you get water delivered to top up your tanks?', 'dropdown', 'water_info', 3, 1, 0, @q1_id, 'yes');

SET @q1b_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q1b_id, 'yes', 'Yes', 1),
(@q1b_id, 'no', 'No', 2);

-- Question 1ca: Municipality or Private Tanker? (depends on Q1b = Yes)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`, `depends_on_question_id`, `depends_on_answer_value`) 
VALUES ('Do you use the Municipality tankers or Private Tankers?', 'dropdown', 'water_info', 4, 1, 0, @q1b_id, 'yes');

SET @q1ca_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q1ca_id, 'municipality', 'Municipality Tanker', 1),
(@q1ca_id, 'private', 'Private Tanker', 2);

-- Question 1cb: Free municipality delivery? (depends on Q1b = Yes)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`, `depends_on_question_id`, `depends_on_answer_value`) 
VALUES ('Do you make use of the one FREE municipality tanker delivery each month?', 'dropdown', 'water_info', 5, 1, 0, @q1b_id, 'yes');

SET @q1cb_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q1cb_id, 'yes', 'Yes', 1),
(@q1cb_id, 'no', 'No', 2);

-- Question 1cc: How regularly do you top up? (depends on Q1b = Yes)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`, `depends_on_question_id`, `depends_on_answer_value`) 
VALUES ('How regularly do you top up your tanks by getting water delivered?', 'dropdown', 'water_info', 6, 1, 0, @q1b_id, 'yes');

SET @q1cc_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q1cc_id, 'weekly', 'Weekly', 1),
(@q1cc_id, 'monthly', 'Monthly', 2),
(@q1cc_id, 'every_other_month', 'Every Other Month', 3);

-- Question 2: Monthly water usage estimate
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`) 
VALUES ('As an estimate how much water does your household use in any given month?', 'dropdown', 'water_info', 7, 1, 1);

SET @q2_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q2_id, '5000', '5000 Litres', 1),
(@q2_id, '10000', '10 000 Litres', 2),
(@q2_id, '15000', '15 000 Litres', 3),
(@q2_id, '20000', '20 000 Litres Plus', 4);

-- Question 3: Number of people in household
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`) 
VALUES ('How many people in your household?', 'dropdown', 'water_info', 8, 1, 1);

SET @q3_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q3_id, '1', '1', 1),
(@q3_id, '2', '2', 2),
(@q3_id, '3', '3', 3),
(@q3_id, '4', '4', 4),
(@q3_id, '5', '5', 5),
(@q3_id, '6', '6', 6),
(@q3_id, '7', '7 Upwards', 7);

-- Question 4: Do you rent out your property?
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`) 
VALUES ('Do you rent out your property?', 'dropdown', 'water_info', 9, 1, 1);

SET @q4_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q4_id, 'yes', 'Yes', 1),
(@q4_id, 'no', 'No', 2);

-- Question 5: Water wise actions (checkbox - multiple)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`) 
VALUES ('Have you implemented water wise daily actions in your household? Please select all relevant options.', 'checkbox', 'water_info', 10, 1, 0);

SET @q5_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q5_id, 'frontloader', 'Frontloader washing machine', 1),
(@q5_id, 'greywater', 'Greywater system for watering your garden or flushing loos', 2),
(@q5_id, 'showering', 'Showering instead of bathing', 3),
(@q5_id, 'notices', 'Notices in your bathroom for guests about water scarcity', 4),
(@q5_id, 'waterwise_plants', 'Waterwise plants in your garden', 5),
(@q5_id, 'rainwater_tanks', 'Installed tanks to harvest rainwater', 6),
(@q5_id, 'waterwise_appliances', 'Using waterwise appliances to help preserve water', 7);

-- Question 6: Willing to submit affidavit?
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`) 
VALUES ('Would you be willing to submit an affidavit based on your above answers and any water data captured if needed?', 'dropdown', 'water_info', 11, 1, 1);

SET @q6_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q6_id, 'yes', 'Yes', 1),
(@q6_id, 'no', 'No', 2),
(@q6_id, 'maybe', 'Maybe', 3);

-- Question 7: Terms agreement (static checkbox with link)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`, `terms_link`) 
VALUES ('I agree to the terms and conditions governing my submission of water data', 'checkbox', 'water_info', 12, 1, 1, '/water-data-terms.php');

SET @q7_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q7_id, 'agreed', 'I agree', 1);

