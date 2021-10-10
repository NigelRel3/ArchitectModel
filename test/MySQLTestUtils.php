<?php

trait MySQLTestUtils	{
	protected function select ( string $query ) : array	{
		$db = $this->getConnection();
		$stmt = $db->query($query);
		return $stmt->fetchAll();
	}

	protected function getConnection(): PDO {
		if ( $this->db === null )	{
			$host = getenv("DB_HOST");
			$user = getenv("DB_USER");
			$password = getenv("DB_PASSWD");
			$database = getenv("DB_DBNAME");
			$db = new PDO("mysql:host={$host};dbname={$database}",
			$user, $password);
			$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->db = $db;
		}
		return $this->db;
	}

	protected function setUp() : void	{
		$db = $this->getConnection();
		if ( is_array($this->setUp) )	{
			foreach ( $this->setUp as $sql )	{
				$db->query($sql);
			}
		}
	}

	protected function insert( array $data, string $statement = null ): void	{
		$db = $this->getConnection();
		$sql = isset($statement) ? $this->insert[$statement] : $this->insert;
		$s = $db->prepare($sql);

		foreach ( $data as $row )	{
			$s->execute($row);
		}
	}

}