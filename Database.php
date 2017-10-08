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
			$foreignTables = array();
			foreach($this->tables as $table)
			{
				echo "##########Checking out ".$table->name.PHP_EOL;
				$createTable = $this->conn->query('SHOW CREATE TABLE '.$table->name)->fetch_assoc();
				$numForeignKeys = substr_count($createTable['Create Table'], 'FOREIGN KEY');

				if($numForeignKeys > 0)
				{
					if($numForeignKeys > 1)
					{
						echo $table->name." determined to be weak entity. Will use db references".PHP_EOL;

						foreach($this->tables as $secondaryTable)
						{
							$foreignTables[] = $this->getForeignsInCreateTableStatement($createTable['Create Table'], $secondaryTable->name);
						}
					}
					else
					{
						// $foreignTables[] = //foreign table's name
						echo $table->name." determined to refer to one other table. Going to store child elements as a encapsulated document.".PHP_EOL;
						foreach($this->tables as $secondaryTable)
						{
							$foreignTables[] = $this->getForeignsInCreateTableStatement($createTable['Create Table'], $secondaryTable->name);
						}
						//var_dump($foreignTables);
					}
				}
				else
				{
					// if(in_array($table->name, $foreignTables))
					// {
					// 	echo $table->name." has already been processed, and determined to be a child entity."PHP_EOL;
					// }
					// else
					// {
						echo $table->name." has no foreign keys, isolated table.".PHP_EOL;
					// }
				}
				echo PHP_EOL;
			}
		}

		public function getForeignsInCreateTableStatement($createTable, $foreignName)
		{
			$constraints = array();

			preg_match('/FOREIGN KEY \(`[0-9A-Za-z_]*`\) REFERENCES `[0-9A-Za-z_]*` \(`[0-9A-Za-z_]*`\)[,]?/',$createTable,$constraints);

			var_dump('Constraints', $constraints);

			foreach($constraints as $constraint)
			{
				$start = 14; //F O R E I G N   K E Y ( `
				$end   = strpos($constraint, '`)');
				$length = $end - $start;
				$foreignKey = substr($constraint, $start, $length);
				// var_dump('Foreign Key', $foreignKey);

				$start = $end + 14 ;//) REFERENCES ` 
				$end = strpos($constraint, '` (',$start);
				$length = $end-$start;
				$tableName = substr($constraint, $start, $end);

				$start = $end + 3; // (`;
				$end = strpos($constraint, '`)',$start);
				$length = $end-$start;
				$matchingColumn = substr($constraint,$start,$end);

				var_dump($foreignKey,$tableName,$matchingColumn);
			}
			// //track last position
			// $lastPos = 0; 
			// $length = strlen($foreignName);
			// $foreignName = "REFERENCES `".$foreignName."`";
			// $toReturnNames = null;
			
			// if($lastPos = strpos($createTable,$foreignName,$lastPos) !== false)
			// {
			// 	$length = strlen($foreignName);
			// 	$lastPos = $lastPos + $length;
			// 	$toReturnNames = substr($createTable, $lastPos, $length);
			// 	var_dump($toReturnNames);
			// }

			// if(isset($toReturnNames)){
			// 	return $toReturnNames;
			// }
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