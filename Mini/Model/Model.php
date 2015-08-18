<?php

namespace Mini\Model;

use PDO;

class Model
{
    /**
     * The database connection
     * @var PDO
     */
	private $db;

    /**
     * When creating the model, the configs for database connection creation are needed
     * @param $config
     */
    function __construct($config)
    {
        // PDO db connection statement preparation
        $dsn = 'mysql:host=' . $config['db_host'] . ';dbname='    . $config['db_name'] . ';port=' . $config['db_port'];

        // note the PDO::FETCH_OBJ, returning object ($result->id) instead of array ($result["id"])
        // @see http://php.net/manual/de/pdo.construct.php
        $options = array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ, PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING);

        // create new PDO db connection
        $this->db = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
	}

    public function addUser($user_id, $name, $email)
    {
        $sql = "INSERT INTO user (user_id, name, email) VALUES (:user_id, :name, :email)";
        $query = $this->db->prepare($sql);
        $parameters = array(':user_id' => $user_id, ':name' => $name, ':email' => $email);
        $query->execute($parameters);
    }

    /**
     * Get a user from database
     * @param int $user_id Id of user
     * @return mixed
     */
    public function getUser($user_id)
    {
        $sql = "SELECT ID, user_id, name, email, g_access_token, refresh_token, expires_in FROM user WHERE user_id = :user_id LIMIT 1";
        $query = $this->db->prepare($sql);
        $parameters = array(':user_id' => $user_id);
        $query->execute($parameters);
        return $query->fetch();
    }

    public function updateToken($user_id, $g_access_token, $refresh_token, $token_type, $expires_in)
    {
        $sql = "UPDATE user SET g_access_token = :g_access_token, refresh_token = :refresh_token, token_type = :token_type, expires_in = :expires_in WHERE user_id = :user_id";
        $query = $this->db->prepare($sql);
        $parameters = array(':g_access_token' => $g_access_token, ':refresh_token' => $refresh_token, ':token_type' => $token_type, ':expires_in' => $expires_in, ':user_id' => $user_id);
        $query->execute($parameters);
    }

}
