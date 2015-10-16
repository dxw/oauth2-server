<?php

class OAuth2Server_DB
{
    public function __construct()
    {
        register_activation_hook(__DIR__.'/../oauth2-server.php', [$this, 'createTables']);
    }

    public function createTables()
    {
        global $wpdb;
        $sql = "
        CREATE TABLE {$wpdb->prefix}oauth2_server_sessions (
            id INT NOT NULL AUTO_INCREMENT,
            client_id TEXT,
            owner_type TEXT,
            owner_id INT,
            created_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            deleted_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            UNIQUE KEY id (id)
        );
        CREATE TABLE {$wpdb->prefix}oauth2_server_access_tokens (
            id INT NOT NULL AUTO_INCREMENT,
            session_id INT,
            access_token TEXT,
            expire_time INT,
            created_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            deleted_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            UNIQUE KEY id (id)
        );
        CREATE TABLE {$wpdb->prefix}oauth2_server_auth_codes (
            id INT NOT NULL AUTO_INCREMENT,
            session_id INT,
            auth_code TEXT,
            expire_time INT,
            created_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            deleted_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            UNIQUE KEY id (id)
        );
        CREATE TABLE {$wpdb->prefix}oauth2_server_refresh_tokens (
            id INT NOT NULL AUTO_INCREMENT,
            access_token_id INT,
            refresh_token TEXT,
            expire_time INT,
            client_id TEXT,
            created_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            deleted_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            UNIQUE KEY id (id)
        );
        ";

        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

new OAuth2Server_DB();
