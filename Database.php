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
			
			//supress warnings on constructor
			$this->conn = @new \mysqli($this->servername, $this->username, $this->pass);
				
			if($this->conn->connect_error)
			{
				echo "Can't connect to MySQL DB. Are you using the correct username / password?".PHP_EOL;
				echo $this->conn->connect_error.PHP_EOL;
				die();
			}
			$this->conn->query('USE '.$this->db);
			
			$this->tables = [];
			$this->output = $output;
			$this->file = fopen($this->output,'w+') or die('Could not open output file');
			$this->getTables();
		}

		public function getTables()
		{
			$queryResult = $this->conn->query("SHOW TABLES;");

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
			$foreignTables;
			foreach($this->tables as $table)
			{
				echo "##########Checking out ".$table->name.PHP_EOL;
				$createTable = $this->conn->query('SHOW CREATE TABLE '.$table->name)->fetch_assoc();
				$numForeignKeys = substr_count($createTable['Create Table'], 'FOREIGN KEY');

				if($numForeignKeys > 0)
				{
					if($numForeignKeys > 1)
					{
						echo $table->name." determined to be weak entity.".PHP_EOL;
					}
					else
					{
						// $foreignTables[] = //foreign table's name
						echo $table->name." determined to refer to one other table. Could be the 'one side' of a one to many relationship.".PHP_EOL;
						//check foreign table.
					}
				}
				else
				{
					// if(in_array($table->name, $foreignTables))
					// {
					// 	echo $table->name." has already been processed, and determined to be a child entity."PHP_EOL
					// }
					// else
					// {
						echo $table->name." has no foreign keys, isolated table.".PHP_EOL;
					// }
				}
				echo PHP_EOL;
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