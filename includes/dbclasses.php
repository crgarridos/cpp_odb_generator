<?php 

class ConnexionDetails{

	public $host, $user, $pass, $dbname;

	function __construct($host, $user, $pass, $dbname){
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
		$this->dbname = $dbname;
	}

	public function __toString() {
        return $this->host."|".$this->user."|".$this->pass."|".$this->dbname;
    }
}

class DatebaseInformation{

    private static $databases;
    private $connection;
    private $details;

    public function __construct($connDetails){
        if(!is_object(self::$databases[$connDetails.''])){
            $details = $this->details = $connDetails;
            $dsn = "mysql:host={$details->host};dbname={$details->dbname}";
            self::$databases[$connDetails.''] = new PDO($dsn, $details->user, $details->pass);
        }
        $this->connection = self::$databases[$connDetails.''];
    }
    
    public function getAttrs($table){
        $propertiesSQL =  file_get_contents("includes/properties.sql");
        $propertiesSQL =  str_replace("&table", $table, $propertiesSQL);
        $propertiesSQL =  str_replace("&db", $this->details->dbname, $propertiesSQL);
        //$this->printSQL($propertiesSQL);

        //$args = func_get_args();
        //array_shift($args);
        $statement = $this->connection->prepare($propertiesSQL);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_OBJ);
    }

    
    public function getRelationsOne($table){
        $relationsSQL =  file_get_contents("includes/relations.sql");
        $relationsSQL =  str_replace("&table", $table, $relationsSQL);
        $relationsSQL =  str_replace("&db", $this->details->dbname, $relationsSQL);
        //$this->printSQL($relationsSQL);

        //$args = func_get_args();
        //array_shift($args);
        $statement = $this->connection->prepare($relationsSQL);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_OBJ);
    }

    public function getRelationsMany($table){
        $relationsSQL =  file_get_contents("includes/relations_many.sql");
        $relationsSQL =  str_replace("&table", $table, $relationsSQL);
        $relationsSQL =  str_replace("&db", $this->details->dbname, $relationsSQL);
        //$this->printSQL($relationsSQL);

        //$args = func_get_args();
        //array_shift($args);
        $statement = $this->connection->prepare($relationsSQL);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_OBJ);
    }

    public function getAllTablesNames(){
        $sql =  "SELECT table_name FROM information_schema.tables WHERE table_schema = '&db' ORDER BY table_name";
        $sql =  str_replace("&db", $this->details->dbname, $sql);
        //$this->printSQL($propertiesSQL);

        //$args = func_get_args();
        //array_shift($args);
        $statement = $this->connection->prepare($sql);
        $statement->execute();
        $names = array();
        //return $statement->fetchAll(PDO::FETCH_OBJ);
        foreach ($statement->fetchAll(PDO::FETCH_OBJ) as $table) {
            $names[] = $table->table_name;
        }
            //print_r($names);
        return $names;
    }

    private function printSQL($sql){
    	echo "<pre>"; 
		print_r($sql);
		echo "</pre>";
    }
}
