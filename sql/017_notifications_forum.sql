
ALTER TABLE notifications
    MODIFY project_id INT NULL,
    ADD COLUMN question_id INT DEFAULT NULL AFTER task_id,
    ADD COLUMN answer_id INT DEFAULT NULL AFTER question_id,
    MODIFY type ENUM('task_assigned','comment_added','mentioned','due_date_changed','task_completed','attachment_uploaded','project_updated','forum_comment') NOT NULL,
    ADD CONSTRAINT fk_notif_question FOREIGN KEY (question_id) REFERENCES forum_questions(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_notif_answer FOREIGN KEY (answer_id) REFERENCES forum_answers(id) ON DELETE CASCADE;
