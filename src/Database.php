<?php
	namespace SimpleDB;

	/**
	* Used to access database and send raw queries.
	* This is named SimpleDB so it doesn't conflict with other libraries.
	*
	* @author Christopher T. Bishop
	* @version 0.1.0
	*/
	final class Database {
		
		private $url; // The URL of the database.
		private $user; // The user accessing the database.
		private $password; // The user's password.
		private $dbName; // The name of the database.

		private $conn = null;


		/**
		* Builds the database object, ready to connect.
		* @author Christopher T. Bishop
		* @since 0.1.0
		*/
		public function __construct($url, $user, $password, $dbName, $charset = '') {
			$this->url = $url;
			$this->user = $user;
			$this->password = $password;
			$this->dbName = $dbName;

			if(count($charset) > 0) {
				$this->setCharSet($charset);
			}
		}


		private function connect() {
			if($this->conn == null) {
				$this->conn = new \mysqli($this->url, $this->user, $this->password, $this->dbName);

				if($this->conn->connect_error) {
					trigger_error('Could not connect to MySQL Server: ' . $this->conn->connect_error, E_USER_ERROR);
					$this->conn = null;
				}
			}
			return $this->conn;
		}

		private function rawQuery($sql){
			$conn = $this->connect();
			$result = $conn->query($sql);

			if($conn->error !== '') {
				trigger_error("MySQL Error: $conn->error", E_USER_NOTICE);
				trigger_error($sql, E_USER_NOTICE);
				$result = null;
			}

			return $result;
		}
		private function rawQueries($sqls) {
			$conn = $this->connect();
			$results = [];

			foreach($sqls as $sql) {
				$result = $conn->query($sql);
				if($conn->error !== '') {
					trigger_error("Mysql Error: $conn->error", E_USER_NOTICE);
					trigger_error($sql, E_USER_NOTICE);
					$result = null;
				}

				$results[] = $result;
			}

			return $results;
		}


		/**
		 * Execute a query.
		 *
		 * @author Christopher T. Bishop
		 * @since 0.1.0
		 * @param string $sql - A MySQL statement.
		 * @return QueryResult
		 */
		public function query($sql) {
			$result = $this->rawQuery($sql);
			$rows = [];

			if($result !== null) {
				while($row = $result->fetch_assoc()) {
					$rows[] = $row;
				}
			}
			return new QueryResult($result, $rows);
		}

		/**
		 * Execute a scalar query. Returns whatever is returned from the query.
		 *
		 * @author Christopher T. Bishop
		 * @since 0.1.0
		 * @param string $sql - A MySQL statement.
		 * @return QueryResult.
		 */
		public function scalar($sql) {
			return new QueryResult($this->rawQuery($sql));
		}


		/**
		 * Select rows from a table.
		 *
		 * @author Christopher T. Bishop
		 * @since 0.1.0
		 * @param string $table
		 * @param array $args
		 * @return QueryResult
		 */
		public function select($table, $args = []) {
			$selecting = (isset($args['select'])) ? $args['select'] : '*';
			$sql = "SELECT $selecting FROM $table";

			// WHERE
			if(isset($args['where'])) {
				$sql .= ' WHERE ' . $args['where'];
			}

			// ORDER BY
			if(isset($args['orderBy'])) {
				$sql .= ' ORDER BY ' . $args['orderBy'];
				if(isset($args['orderDir'])) {
					$sql .= ' ' . $args['orderDir'];
				}
			}

			// LIMIT AND PAGANATION
			if(isset($args['limit'])) {
				$sql .= ' LIMIT ' . $args['limit'];
				if(isset($args['page'])) {
					$page = $args['page'] - 1;
					if($page < 0) {
						$page = 0;
					}

					$sql .= ' OFFSET ' . $page * $args['limit'];
				}
				elseif(isset($args['offset'])) {
					$sql .= ' OFFSET ' . $args['offset'];
				}
			}
			$sql .= ";";

			return $this->query($sql);
		}

		/**
		 * Count the number of rows that meet the condition.
		 *
		 * @author Christopher T. Bishop
		 * @since 0.1.0
		 * @param string $table
		 * @param string $condition - An optional condition the count should follow.
		 * @return int The number of rows in the table with the specific condition.
		 */
		public function count($table, $condition = '') {
			$sql = "SELECT count(*) as total FROM $table";
			if($condition !== '') {
				$sql .= " WHERE $condition";
			}

			$results = $this->rawQuery($sql);
			return $results->fetch_assoc()['total'];
		}

		/**
		 * Count the number of pages that meet the condition.
		 *
		 * @author Christopher T. Bishop
		 * @since 0.1.0
		 * @param string $table
		 * @param string $condition - An optional condition the count should follow.
		 * @param int $limit - How many rows are on each page.
		 * @return int The number of pages the table has with the condition and limit.
		 */
		public function pageCount($table, $condition, $limit) {
			$count = $this->count($table, $condition);
			return ceil($count / $limit);
		}

		/**
		 * Inserts a row into a table. Returns the ID of the row added.
		 *
		 * @author Christopher T. Bishop
		 * @since 0.1.0
		 * @param string $table
		 * @param array $row
		 * @return QueryResult
		 */
		public function insert($table, $row) {
			$columns = '';
			$values = '';
			foreach($row as $key => $value) {
				$columns .= "$key, ";

				if(is_string($value)) $value = $this->wrap($value);
				$values .= "$value, ";
			}

			$columns = substr($columns, 0, strlen($columns) - 2);
			$values = substr($values, 0, strlen($values) - 2);

			$sql = "INSERT INTO $table ($columns) VALUES ($values);";
			$results = $this->rawQueries([
				$sql,
				'SELECT LAST_INSERT_ID() as id;'
			]);

			if($results[0] != null && $results[1] != null) {
				$row = $results[1]->fetch_assoc();
				return new QueryResult($results[0], [$row]);
			}
			else{
				return null;
			}
		}

		/**
		 * Update all rows in a table that meet the condition.
		 * If no condition is given then update all rows.
		 *
		 * @author Christopher T. Bishop
		 * @since 0.1.0
		 * @param string $table
		 * @param array $row
		 * @param string $condition
		 * @return QueryResult
		 */
		public function update($table, $row, $condition = '') {
			$cev = '';
			foreach($row as $key => $value) {
				$cev .= "$key=";

				if(is_string($value)) $value = $this->wrap($value);
				$cev .= "$value, ";

				$cev = substr($cev, 0, strlen($cev) - 2);
				$sql = "UPDATE $table SET $cev";
				if($condition !== ''){
					$sql .= " WHERE $condition";
				}
			}

			return $this->scalar($sql . ';');
		}

		/**
		 * Delete rows from a table that meet the condition.
		 *
		 * @author Christopher T. Bishop
		 * @since 0.1.0
		 * @param string $table
		 * @param string $column
		 * @param mixed $value
		 * @return QueryResult
		 */
		public function delete($table, $condition){
			$sql = "DELETE FROM $table WHERE $condition";
			return $this->scalar($sql);
		}

		/**
		 * Escape a string in quotes.
		 *
		 * @author Christopher T. Bishop
		 * @since 0.1.0
		 * @param string $str
		 * @return string
		 */
		public function wrap($str) {
			$conn = $this->connect();
			$wrapped = "'" . mysqli_real_escape_string($conn, $str) . "'";

			return $wrapped;
		}

		public function getCharSet() {
			$conn = $this->connect();
			return $conn->character_set_name();
		}
		public function setCharSet($charset) {
			$conn = $this->connect();
			return $conn->set_charset($charset);
		}

		/**
		 * Creates a random string.
		 *
		 * @author Christopher T. Bishop
		 * @since 0.1.0
		 * @param int $count - How long the string should be.
		 * @param int $prefix - A string that goes before the random string. The prefix length is part of the $count.
		 * @param string $haystack - The available characters that the random string can have.
		 */
		public static function randId($count, $prefix = '', $haystack = 'aAbBcCdDeEfFgGhHiIjJkKlLmMnNoOpPqQrRsStTuUvVwWxXyYzZ0123456789') {
			$str = '';
			for($i = 0; $i < $count - strlen($prefix); $i++){
			$str .= substr($haystack, rand(0, strlen($haystack) - 1), 1);
			}

			return $prefix + $str;
		}

	}

	/**
	 * This is how query results are stored.
	 * @author Christopher T. Bishop
	 * @version 0.1.0
	 */
	final class QueryResult{

		public $resultSet;
		public $rows;


		public function __construct($resultSet, $rows = []){
			$this->resultSet = $resultSet;
			$this->rows = $rows;
		}


		public function count(){
			return count($this->rows);
		}


		public function toJson($prefix = '') {
			$data;
			$prefixLength = strlen($prefix);
			if($prefixLength > 0) {
				$data = [];
				foreach($this->rows as $row) {
					$datum = [];
					foreach($row as $key => $value) {
						$datum[substr($key, $prefixLength)] = $value;
					}

					$data[] = $datum;
				}
			}
			else {
				$data = $this->rows;
			}

			return json_encode($data, JSON_UNESCAPED_UNICODE);
		}

	}
?>