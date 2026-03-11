-- Add chart_animation preference to users table
ALTER TABLE `users`
    ADD COLUMN `chart_animation` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=animations activées, 0=désactivées'
    AFTER `alert_dnd`;
