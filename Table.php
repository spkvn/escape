<?php
namespace Escape
{
	Class Table
	{
		public function __construct($name, $data = null)
		{
			$this->name  = $name;
			$this->data = $data;
		}

		public function setData(\mysqli_result $result)
		{
			$this->data = [];
			if($result->num_rows > 0)
			{
				while($row = $result->fetch_assoc())
				{
					array_push($this->data, $row);
				}
			}
			else
			{
				echo 'Empty Table';
			}
		}

		public function toAssocArray()
		{
			return $encapsulatingArr = [$this->name => $this->data];
		}

		public $name;
		public $data;
		public $relationships;//['tablename' => 'x', 'localKey' => 'x_id','foreignKey' => 'x.id']
	}
}
?>