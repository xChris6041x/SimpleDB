<?php
    namespace SimpleDB;
    require_once('Table.php');


    /**
     * A class for creating and logging in users.
     *
     * @author Christopher T. Bishop
     * @version 0.1.0
     */
     class Security {

        private $db;
        private $accounts;
        private $roles;

        private $loginNameField;
        private $loginPasswordField;
        
        public function __construct($db, $args = []) {
            $this->db = $db;

            $this->loginNameField = isset($args['loginName']) ?: 'account_name';
            $this->loginPasswordField = isset($args['loginPassword']) ?: 'account_password';

            $this->accounts = new Table($this->db, 'account', 'account_id');
            $this->roles = new Table($this->db, 'role', 'role_id');
        }


        /**
         * Log in the website using a name and password.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param string $name
         * @param string $password
         * @return bool Whether the user was able to log in with those credentials.
         */
        public function login($name, $password) {
            if(session_status() != PHP_SESSION_ACTIVE) return false;
            $id = $this->verifiyCredentials($name, $password);

            if($id == -1) {
                return false;
            }
            else {
                $_SESSION[SECURITY_LOGIN_NAME] = $id;
                return true;
            }
        }


        /**
         * Check whether the credentials are valid in the database.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param string $name
         * @param string $password
         * @return int The ID of the account (or -1 for not valid).
         */
        public function verifiyCredentials($name, $password){
            $row = $this->getAccountByName($name);
            if($row != null) {
                if(password_verify($password, $row[$this->loginPasswordField])){
                    return $row['account_id'];
                }
            }
            return -1;
        }

        /**
         * Check whether the password for the logged in account is valid.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param Array $account
         * @param string $password
         */
        public function verifyPassword($account, $password){
            return password_verify($password, $account[$this->loginPasswordField]);
        }

        /**
         * Sign up to the website using a name, password and other information (for custom fields).
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param string $name
         * @param string $password
         * @param Array $info
         * @return Array | NULL Gives the account array that was created or null if it failed.
         */
        public function signup($name, $password, $info){
            $info[$this->loginNameField] = $name;
            $info[$this->loginPasswordField] = password_hash($password, PASSWORD_DEFAULT);

            $result = $this->accounts->insert($info);
            if($result->count() > 0){
                return $result->rows[0];
            }
            else{
                return null;
            }
        }

        /**
         * Update the logged in user's password and other information.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param string $password
         * @param string $info
         * @return bool Whether the update was successful.
         */
        public function update($password, $info){
            if(!isset($_SESSION[SECURITY_LOGIN_NAME])) return false;

            if($password != null) {
                $info[$this->loginPasswordField] = password_hash($password, PASSWORD_DEFAULT);
            }

            $result = $this->accounts->updateOne($info, $_SESSION[SECURITY_LOGIN_NAME]);
            return $result->resultSet;
        }

        /**
         * Update the a user's information.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param string $password
         * @param string $info
         * @return bool Whether the update was successful.
         */
        public function updateById($id, $info){
            $result = $this->accounts->updateOne($info, $id);
            return $result->resultSet;
        }

        /**
         * Get the logged in user row.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @return NULL|array
         */
        public function getLoggedInAccount(){
            if(isset($_SESSION[SECURITY_LOGIN_NAME])){
                return $this->getAccount($_SESSION[SECURITY_LOGIN_NAME]);
            }
            else{
                return null;
            }
        }

        /**
         * Get an account by it's ID.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param int $id
         * @return NULL|array
         */
        public function getAccount($id){
            $account = null;
            $result = $this->accounts->selectOne($id);

            if($result->count() == 1){
                $account = $result->rows[0];
            }
            return $account;
        }

        /**
         * Get an account by it's name.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param string $name
         * @return Array|NULL The account as an array (or null if it can't be found).
         */
        public function getAccountByName($name){
            $result = $this->accounts->select([
            'where' => "$this->loginNameField = '$name'"
            ]);

            if($result->count() == 1){
                return $result->rows[0];
            }
            else{
                return null;
            }
        }

        public function getAccounts(){ return $this->accounts; }

        /**
         * Check if an account has any role by it's ID.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param number $id
         * @param array $roles
         */
        public function hasAnyRoleById($id, $roles){
            if($this->getAccount($id) == null) return false;

            $hasRole = false;
            $result = $this->roles->select([
                'where' => 'account_id = ' . $id
            ]);

            foreach($result->rows as $row){
                if($hasRole) break;
                    foreach($roles as $role) {
                        if($row['role_name'] == $role){
                        $hasRole = true;
                        break;
                    }
                }
            }

            return $hasRole;
        }

        /**
         * Check if a user has any role.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param array $roles
         */
        public function hasAnyRole($roles){
            if(session_status() != PHP_SESSION_ACTIVE || !isset($_SESSION[SECURITY_LOGIN_NAME])) return false;
            return $this->hasAnyRoleById($_SESSION[SECURITY_LOGIN_NAME], $roles);
        }


        /**
         * Log out of the website.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         */
        public static function logout(){
            if(session_status() != PHP_SESSION_ACTIVE) return;
            unset($_SESSION[SECURITY_LOGIN_NAME]);
        }

        /**
         * Check whether a user is logged in.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @return whether a user is logged in.
         */
        public static function isLoggedIn(){
            if(session_status() != PHP_SESSION_ACTIVE) return false;

            if(isset($_SESSION[SECURITY_LOGIN_NAME])){
                return true;
            }
            else{
                return false;
            }
        }

     }

?>