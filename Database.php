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
					array_push($this->tables, new \Escape\Table($row['Tables_in_'.$this->db],null,null));
				}
			}
			else
			{
				echo "No Tables found.";
			}
		}

		public function fillTables()
		{
			$processedTables = array();
			$dbJSON = array();
			foreach($this->tables as $table)
			{
				echo "##########Checking out ".$table->name.PHP_EOL;
				$createTable = $this->conn->query('SHOW CREATE TABLE '.$table->name)->fetch_assoc();
				$numForeignKeys = substr_count($createTable['Create Table'], 'FOREIGN KEY');

				if($numForeignKeys > 0)
				{
					// if($numForeignKeys > 1)
					// {
					// 	echo $table->name." determined to be weak entity. Will use db references".PHP_EOL;

					// 	$foreignTables = $this->getForeignsInCreateTableStatement($createTable['Create Table']);
						
					// 	foreach($foreignTables as $ft)
					// 	{
					// 		$processedTables[] = $ft['foreignTable'];
					// 	}
					// }
					// else
					// {
						echo $table->name." determined to refer to one other table. Going to store child elements as a encapsulated document.".PHP_EOL;
						
						$foreignTables = $this->getForeignsInCreateTableStatement($createTable['Create Table']);
						
						foreach($foreignTables as $ft)
						{
							//empty array for entire table.
							$parentJSON = array();

							//get all rows of a table.
							$parentRow = $this->conn->query('SELECT * FROM '.$table->name);
							
							//while there are still parents to be processed
							while($pRow = $parentRow->fetch_assoc())
							{
								//select all rows which are children of this parent
								$query = "SELECT DISTINCT c.* FROM ".$table->name." AS p".
										 " INNER JOIN ".$ft['foreignTable'].
										 " AS c ON c.".$ft['matchingColumn'].
										 " = p.".$ft['foreignKey'].
										 " WHERE c.".$ft['matchingColumn']." = ".$pRow[$ft['foreignKey']];
								$childRow = $this->conn->query($query);

								//store all children in array
								$childrenJSON = array();

								// get all child elements into an array
								while($cRow = $childRow->fetch_assoc())
								{
									$childrenJSON[] = $cRow;
								}

								//put children in place of parent foreign key
								$pRow[$ft['foreignKey']] = $childrenJSON;

								$parentJSON[] = $pRow;
							}
							
							//parentJSON contains the entire table, with embedded documents.
							$dbJSON[] = [$table->name => $parentJSON];

							$processedTables[] = $ft['foreignTable'];
						}
					// }
				}
				else
				{
					if(in_array($table->name, $processedTables))
					{
						echo $table->name." has already been processed, and determined to be a child entity.".PHP_EOL;
					}
					else
					{
						//empty array for entire table.
						$parentJSON = array();

						//get all rows of a table.
						$parentRow = $this->conn->query('SELECT * FROM '.$table->name);
						
						//while there are still parents to be processed
						while($pRow = $parentRow->fetch_assoc())
						{
							$parentJSON[] = $pRow;
						}
						
						//parentJSON contains the entire table, with embedded documents.
						$dbJSON[] = [$table->name => $parentJSON];

						// $processedTables[] = $ft['foreignTable'];
					}
				}
				echo PHP_EOL;
			}
			var_dump(json_encode($dbJSON));
		}

		public function getForeignsInCreateTableStatement($createTable)
		{
			$constraints = [];
			$foreignTables = array();

			preg_match_all('/FOREIGN KEY \(`[0-9A-Za-z_]*`\) REFERENCES `[0-9A-Za-z_]*` \(`[0-9A-Za-z_]*`\)[,]?/',$createTable,$constraints);
			
			foreach($constraints[0] as $constraint)
			{
				$start = 14; //F O R E I G N   K E Y ( `
				$end   = strpos($constraint, '`)');
				$length = $end - $start;
				$foreignKey = substr($constraint, $start, $length);
				echo "Foreign Key: ".$foreignKey.PHP_EOL;

				$start = $end + 15 ;//) REFERENCES ` ` 
				$end = strpos($constraint, '`',$start);
				$length = $end-$start;
				$tableName = substr($constraint, $start, $length);
				echo "Table Name: ".$tableName.PHP_EOL;

				$start = $end + 4; // (`;
				$end = strpos($constraint, '`)',$start);
				$length = $end-$start;
				$matchingColumn = substr($constraint,$start,$length);
				echo "matchingColumn: ".$matchingColumn.PHP_EOL;

				$foreignTables[] = [
					'foreignKey' => $foreignKey, 
					'foreignTable' => $tableName, 
					'matchingColumn' => $matchingColumn
				];
			}
			return $foreignTables;
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