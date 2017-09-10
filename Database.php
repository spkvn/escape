<?php
namespace Escape
{
	Class Database
	{
		public function __construct($servername = 'localhost', 
									$username = 'root',
									$pass = '',
									$database = null,
									$output = 'output.json')
		{
			$this->servername = $servername;
			$this->username = $username;
			$this->pass = $pass;
			if($pass = '')
			{
				echo 'Password was not set, expect failure.';
			}
			$this->db = $database;
			try
			{
				$this->conn = new \mysqli($this->servername, $this->username, $this->pass);
				$this->conn->query('USE '.$this->db);
			}
			catch (Exception $e)
			{
				echo "Exception Occurred, was unable to connect to MySQL Server. Are you using the correct permissions?";
				echo $e;
			}

			$this->tables = [];
			$this->output = $output;
			$this->file = fopen($this->output,'w+') or die('Could not open output file');
			$this->getTables($this->conn->query("SHOW TABLES;"));
		}

		public function getTables(\mysqli_result $queryResult)
		{
			if($queryResult->num_rows > 0)
			{
				while($row = $queryResult->fetch_assoc())
				{
					array_push($this->tables, new \Escape\Table($row['Tables_in_'.$this->db]));
				}
			}
			else
			{
				echo "No Tables found.";
			}
		}

		public function fillTables()
		{
			foreach($this->tables as $table)
			{
				$result = $this->conn->query('SELECT * FROM '.$table->name);
				$table->setData($result);
			}
		}

		public function writeOutput()
		{
			$fileContent = $this->convertToJSON(); 
			fWrite($this->file,$fileContent);
			fClose($this->file);
		}
		public function convertToJSON()
		{
			$jsonContent = '';
			$dataAsArr = [];
			foreach($this->tables as $table)
			{
				array_push($dataAsArr, $table->toAssocArray());
			}
			$jsonContent = json_encode($dataAsArr);
			return $jsonContent;
		}
		private $servername; 
		private $username; 
		private $pass; 
		private $db; 
		public $conn;
		public $tables; 
		public $json;
		public $file;
	}
}
?>