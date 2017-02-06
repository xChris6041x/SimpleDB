<?php
    namespace SimpleDB;

    require_once('Database.php');

    /**
     * Used to access databases and to send queries to specific tables.
     *
     * @author Christopher T. Bishop
     * @version 0.1.0
     */
    class Table {

        protected $db;
        protected $name;
        protected $primaryKey;


        public function __construct(Database $db, $name, $primaryKey) {
            $this->db = $db;
            $this->name = $name;
            $this->primaryKey = $primaryKey;
        }


        /**
         * Select rows from the table.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param array $args
         * @return QueryResult
         */
        public function select($args = []) {
            return $this->db->select($this->name, $args);
        }

        /**
         * Select one row which meets the condition primaryKey=$value.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param mixed $value
         * @return QueryResult
         */
        public function selectOne($value) {
            return $this->select([
                'where' => "$this->primaryKey=$value",
                'limit' => 1
            ]);
        }

        /**
         * Count the number of rows that meet the condition.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param string $condition
         * @return int
         */
        public function count($condition = '') {
            return $this->db->count($this->name, $condition);
        }

        /**
         * Inserts a row into the table.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param array $row
         * @return QueryResult The result with the row that was created as the first row.
         */
        public function insert($row) {
            $insertResult = $this->db->insert($this->name, $row);
            $id = $insertResult->rows[0]['id'];

            $selectResult = $this->selectOne($id);

            $result = null;
            if($selectResult->count() == 1) {
                $result = new QueryResult($insertResult->resultSet, $selectResult->rows);
            }

            return $result;
        }

        /**
         * Update all rows in the table that meet the condition.
         * If no condition is given then update all rows.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param array $row
         * @param string $condition
         * @return QueryResult
         */
        public function update($row, $condition) {
            return $this->db->update($this->name, $row, $condition);
        }

        /**
         * Update a single row that meets the condition primaryKey=$value.
         * If no condition is given then update all rows.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param array $row
         * @param mixed $value
         * @return QueryResult
         */
        public function updateOne($row, $value) {
            return $this->update($row, "$this->primaryKey='$value'");
        }

        /**
         * Delete rows from the table that meet the condition.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param string $condition
         * @return QueryResult
         */
        public function delete($condition) {
            return $this->db->delete($this->name, $condition);
        }

        /**
         * Delete a single row that meets the condition primaryKey=$value.
         *
         * @author Christopher T. Bishop
         * @since 0.1.0
         * @param mixed $value
         * @return QueryResult
         */
        public function deleteOne($value) {
            return $this->delete("$this->primaryKey=$value");
        }

    }

?>