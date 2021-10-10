<?php

use PHPUnit\Framework\TestCase;
use wh\data\wh\Product;
use wh\data\ArrayStore;
use wh\data\MySQL;
use wh\data\wh\Order;
use wh\data\wh\TypeTest;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__. "/MySQLTestUtils.php";

class MySQLTest extends TestCase   {
	use MySQLTestUtils;

    private $db;

    protected $setUp = ["delete from Review",
    		"delete from ProductCategories",
    		"delete from Activity",
    		"delete from StowedProduct",
    		"delete from SuppliedProducts",
    		"delete from Product",
    	"delete from `Order`",
    	"delete from Customer",
    	"delete from TypeTest"
    ];
    protected $insert = "insert into Product (id, tag, description)
				values ( ?, ?, ?)";

    public function testGetTableName () {
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$this->assertEquals("Product", $p->getTableName());
    }

    public function testFields () {
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$this->assertEquals(['id', 'tag', 'description'], $p->getFields());
    }

    public function testColumnNames () {
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$this->assertEquals(['`Product`.`id`', '`Product`.`tag`', '`Product`.`description`'],
    			$p->getColumnNames());
    }

    public function testGet () {
    	$this->insert([[1, 'p1', 'product 1'],
    			[2, 'p2', 'product 2']
    	]);
    	$db = $this->getConnection();
		$p = new Product($db);

		$this->assertTrue($p->fetch([2]));
		$this->assertEquals("p2", $p->tag);
    }

    public function testGetActualGet () {
    	$this->insert([[1, 'p1', 'product 1'],
    			[2, 'p2', 'product 2']
    	]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$this->assertTrue($p->fetch([2]));
    	$this->assertEquals(["id" => 2, "tag"=>'p2', "description" => 'product 2'],
    			$p->get());
    }

    public function testGetWhere () {
    	$this->insert([[1, 'p1', 'product 1'],
    			[2, 'p2', 'product 2']
    	]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$products = $p->fetchWhere("tag = :tag", ["tag" => "p2"] );
    	$this->assertEquals('product 2', $products[0]->description);
    }


    public function testGetWhereKeyMismatch () {
    	$this->insert([[1, 'p1', 'product 1'],
    			[2, 'p2', 'product 2']
    	]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$this->expectException(\PDOException::class);
    	$this->expectExceptionMessage("SQLSTATE[HY093]: Invalid parameter number:" .
    			" parameter was not defined");
    	$products = $p->fetchWhere("tag = :tag", ["id" => 1] );
    }

    public function testGetWhereOrder () {
    	$this->insert([[1, 'p1', 'product 1'],
    		[2, 'p2', 'product 2'],
    		[3, 'p2', 'product 3']
    	]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$products = $p->fetchWhere("id > :id", ["id" => "1"], "id desc" );
    	$this->assertCount(2, $products);
    	$this->assertEquals(3, $products[0]->id);
    	$this->assertEquals(2, $products[1]->id);
    }

    public function testGetNotFound () {
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$this->assertFalse($p->fetch([1]));
    }

    public function testGetInvalidColumn () {
    	$this->insert([[1, 'p1', 'product 1']]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$this->assertTrue($p->fetch([1]));
    	$this->expectException(InvalidArgumentException::class);
    	$this->expectExceptionMessage("Unknown variable: tag1");
    	$p->tag1;
    }

    public function testGetAll () {
    	$this->insert([[1, 'p1', 'product 1'],
    		[2, 'p2', 'product 2'],
    		[3, 'p2', 'product 3']
    	]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$products = $p->fetchAll();
    	$this->assertCount(3, $products);
    	$this->assertEquals(1, $products[0]->id);
    	$this->assertEquals(2, $products[1]->id);
    	$this->assertEquals(3, $products[2]->id);
    }

    public function testGetAllOrder () {
    	$this->insert([[1, 'p1', 'product 1'],
    		[2, 'p2', 'product 2'],
    		[3, 'p2', 'product 3']
    	]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$products = $p->fetchAll("id desc" );
    	$this->assertCount(3, $products);
    	$this->assertEquals(3, $products[0]->id);
    	$this->assertEquals(2, $products[1]->id);
    	$this->assertEquals(1, $products[2]->id);
    }

    public function testGetIn () {
    	$this->insert([[1, 'p1', 'product 1'],
    			[2, 'p2', 'product 2'],
    			[21, 'p21', 'product 21'],
    			[22, 'p22', 'product 22'],
    			[23, 'p23', 'product 23'],
    			[24, 'p24', 'product 24']
    	]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$products = $p->fetchIn([[2],[23],[21]]);
    	$this->assertCount(3, $products);
    	$this->assertEquals("p2", $products[0]->tag);
    	$this->assertEquals("p21", $products[1]->tag);
    	$this->assertEquals("p23", $products[2]->tag);
    }

    public function testGetInCompositeKey () {
    	$this->insert([[1, 'p1', 'product 1'],
    			[2, 'p2', 'product 2'],
    			[21, 'p21', 'product 21'],
    			[22, 'p22', 'product 22'],
    			[23, 'p23', 'product 23'],
    			[24, 'p24', 'product 24']
    	]);
    	$db = $this->getConnection();
    	$p = new class($db) extends MySQL	{
    		protected static $table = "Product";
    		protected static $fields = ["id", "tag", "description"];
    		protected static $pk = ["id", "tag"];
    	};

    	$products = $p->fetchIn([[2, "p2"],[23, "p23"],[21, "p21"]]);
    	$this->assertCount(3, $products);
    	$this->assertEquals("p2", $products[0]->tag);
    	$this->assertEquals("p21", $products[1]->tag);
    	$this->assertEquals("p23", $products[2]->tag);
    }

    public function testGetRaw () {
    	$this->insert([[1, 'p1', 'product 1'],
    		[2, 'p2', 'product 2'],
    		[21, 'p21', 'product 21'],
    		[22, 'p22', 'product 22'],
    		[23, 'p23', 'product 23'],
    		[24, 'p24', 'product 24']
    	]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$products = $p->fetchRAW("select sum(id) as ids
				from Product
				where id < :id", ["id" => 20]);
    	$this->assertCount(1, $products);
    	$this->assertEquals(3, $products[0]['ids']);
    }

    public function testExecuteRaw () {
    	$this->insert([[1, 'p1', 'product 1'],
    		[2, 'p2', 'product 2'],
    		[21, 'p21', 'product 21'],
    		[22, 'p22', 'product 22'],
    		[23, 'p23', 'product 23'],
    		[24, 'p24', 'product 24']
    	]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$this->assertTrue($p->executeRAW("delete from Product
				where id < :id", ["id" => 20]));
    	$products = $p->fetchRAW("select count(id) as ids
				from Product");
    	$this->assertCount(1, $products);
    	$this->assertEquals(4, $products[0]['ids']);
    }

    public function testGetSQL () {
    	$this->insert([[1, 'p1', 'product 1'],
    			[2, 'p2', 'product 2'],
    			[21, 'p21', 'product 21']
    	]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$products = $p->fetchSQL('FROM Product WHERE id > :pid limit 1', ['pid' => 1]);
    	$this->assertCount(1, $products);
    	$this->assertEquals("p2", $products[0]->tag);
    }

    public function testSet () {
    	$db = $this->getConnection();
    	$p = new Product($db);
    	$p->set(["id" => 1, "tag" => "t1", "description" => 'product 1']);
    	$this->assertEquals(1, $p->id);
    	$this->assertEquals('t1', $p->tag);
    	$this->assertEquals('product 1', $p->description);
    }

    public function testSetPartial () {
    	$db = $this->getConnection();
    	$p = new Product($db);
    	$p->set(["id" => 1, "description" => 'product 1']);
    	$this->assertEquals(1, $p->id);
    	$this->assertEquals(null, $p->tag);
    	$this->assertEquals('product 1', $p->description);
    }

    public function testSetInvalidColumn () {
    	$this->insert([[1, 'p1', 'product 1']]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$this->assertTrue($p->fetch([1]));
    	$this->expectException(InvalidArgumentException::class);
    	$this->expectExceptionMessage("Unknown variable: tag1");
    	$p->tag1 = "a";
    }

    public function testBatchSetInvalidColumn () {
    	$db = $this->getConnection();
    	$p = new Product($db);
    	$this->expectException(\Exception::class);
    	$this->expectExceptionMessage("Unknown variable: description1");
    	$p->set(["id" => 1, "description1" => 'product 1']);
    }

    public function testIsSet () {
    	$db = $this->getConnection();
    	$p = new Product($db);
    	$this->assertFalse(isset($p->id));
    	$p->id = 1;
    	$this->assertTrue(isset($p->id));
    }

    public function testInsert () {
    	$db = $this->getConnection();
    	$p = new Product($db);
    	$p->set(["id" => 1, "tag" => 'p1', "description" => 'product 1']);
    	$products = $p->insert();
    	$this->assertEquals(1, $p->id);
    }

    public function testInsertAuto () {
    	$db = $this->getConnection();
    	$p = new Product($db);
    	$p->set(["tag" => 'p1', "description" => 'product 1']);
    	$products = $p->insert();
    	$this->assertNotNull($p->id);
    }

    public function testInsertTagNotSet () {
    	$db = $this->getConnection();
    	$p = new Product($db);
    	$p->set(["id" => 1, "description" => 'product 1']);
    	$this->expectException(\PDOException::class);
    	$products = $p->insert();
    }

    public function testInsertOnePrepare () {
    	$stmtMock = $this->createMock(\PDOStatement::class);
    	$db = $this->createMock(\PDO::class);

    	$db->expects($this->once())
    		->method('prepare')
    		->willReturn($stmtMock);
    	$stmtMock->expects($this->exactly(2))
    		->method('execute')
    		->willReturn(true);

    	$p = new Product($db);
    	$p->set(["id" => 1, "tag" => 'p1', "description" => 'product 1']);
    	$products = $p->insert();
    	$p1 = new Product($db);
    	$p->set(["id" => 2, "tag" => 'p1', "description" => 'product 1']);
    	$products = $p->insert();
    }

    public function testUpdate () {
    	$this->insert([[1, 'p1', 'product 1']]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$this->assertTrue($p->fetch([1]));
    	$this->assertEquals("p1", $p->tag);
    	$p->tag = "pu1";
    	$this->assertTrue($p->update());
    	$this->assertTrue($p->fetch([1]));
    	$this->assertEquals("pu1", $p->tag);
    }

    public function testUpdateWithSet () {
    	$this->insert([[1, 'p1', 'product 1']]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$p->set(["id" => 1, "tag" => 'p1u', "description" => 'product 1']);
    	$p->update();
    	$this->assertTrue($p->fetch([1]));
    	$this->assertEquals("p1u", $p->tag);
    }

    public function testUpdateMock () {
    	$selMock = $this->createMock(\PDOStatement::class);
    	$updMock = $this->createMock(\PDOStatement::class);
    	$db = $this->createMock(\PDO::class);

    	$db->expects($this->exactly(2))
    		->method('prepare')
    		->will($this->onConsecutiveCalls($selMock, $updMock));

    	$selMock->expects($this->once())
	    	->method('execute')
	    	->willReturn(true);

	    $selMock->expects($this->exactly(2))
	    	->method('fetch')
	    	->will($this->onConsecutiveCalls(["id" => 1, "tag" => "p1", "description" => "d1"]
	    			, false));

    	$updMock->expects($this->once())
	    	->method('execute')
	    	->with(["id" => 1, "tag" => "pu1", "description" => "d1"])
	    	->willReturn(true);

    	$p = new Product($db);

    	$this->assertTrue($p->fetch([1]));
    	$this->assertEquals("p1", $p->tag);
    	$p->tag = "pu1";
    	$this->assertTrue($p->update());
    }

    public function testUpdateMockCountPrep () {
    	$selMock = $this->createMock(\PDOStatement::class);
    	$updMock = $this->createMock(\PDOStatement::class);
    	$db = $this->createMock(\PDO::class);

    	$db->expects($this->exactly(2))
    		->method('prepare')
 		   	->will($this->onConsecutiveCalls($selMock, $updMock));

    	$selMock->expects($this->once())
	    	->method('execute')
	    	->willReturn(true);

	    $selMock->expects($this->exactly(2))
	    	->method('fetch')
	    	->will($this->onConsecutiveCalls(["id" => 1, "tag" => "p1", "description" => "d1"]
	    			, false));

    	$updMock->expects($this->exactly(2))
	    	->method('execute')
	    	->with(["id" => 1, "tag" => "pu1", "description" => "d1"])
	    	->willReturn(true);

    	$p = new Product($db);

    	$this->assertTrue($p->fetch([1]));
    	$this->assertEquals("p1", $p->tag);
    	$p->tag = "pu1";
    	$this->assertTrue($p->update());
    	$this->assertTrue($p->update());
    }

    public function testDelete () {
    	$this->insert([[1, 'p1', 'product 1'],
    			[2, 'p2', 'product 2']
    	]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$this->assertTrue($p->fetch([2]));
    	$this->assertTrue($p->delete());
    	$this->assertFalse($p->fetch([2]));
    }

    public function testGetCachedSQL () {
    	$this->insert([[1, 'p1', 'product 1'],
    			[2, 'p2', 'product 2']
    	]);
    	$db = $this->getConnection();
    	$p = new Product($db);

    	$this->assertTrue($p->fetch([2]));
    	$this->assertEquals("p2", $p->tag);

    	$p2 = new Product($db);
    	$this->assertTrue($p2->fetch([1]));
    	$this->assertEquals("p1", $p2->tag);
    }

    public function testSetValidate () {
    	$db = $this->getConnection();
    	$p = new Product($db);
    	$p->set(["id" => 1, "tag" => "t1", "description" => 'product 1']);
    	$this->assertEquals([], $p->validate());
    }

    public function testSetValidateInvalid () {
    	$db = $this->getConnection();
    	$p = new Product($db);
    	$p->set(["id" => 0, "tag" => null,
    			"description" => str_repeat("a", 201)]);
    	$this->assertEquals(['Invalid id \'0\'', 'tag cannot be null',
    			'Max length of description is 200, actual 201'
    	], $p->validate());

    	$p->set(["id" => 1, "tag" => str_repeat("a", 201),
    			"description" => null]);
    	$this->assertEquals(['Max length of tag is 45, actual 201'
    	], $p->validate());

    	$p->set(["id" => 1, "tag" => 'a',
    			"description" => null]);
    	$this->assertEquals(['Min length of tag is 2, actual 1'
    	], $p->validate());
    }

    public function testGetDate () {
    	$db = $this->getConnection();
    	$db->query("INSERT INTO `Customer` (`id`, `name`, `address`, `custref`)
						VALUES (1, 'a', '', '')");
    	$db->query("INSERT INTO `Order` (`id`, `placed`, `customerID`, `despatched`)
					VALUES (1, CURRENT_TIMESTAMP, 1, NULL)");

    	$p = new Order($db);

    	$this->assertTrue($p->fetch([1]));
    	$this->assertEquals(1, $p->customerID);
		$this->assertInstanceOf(DateTime::class, $p->placed);
    }

    public function testSetDate () {
    	$db = $this->getConnection();
    	$db->query("INSERT INTO `Customer` (`id`, `name`, `address`, `custref`)
						VALUES (1, 'a', '', '')");
    	$db->query("INSERT INTO `Order` (`id`, `placed`, `customerID`, `despatched`)
					VALUES (1, CURRENT_TIMESTAMP, 1, NULL)");

    	$p = new Order($db);

    	$this->assertTrue($p->fetch([1]));
    	$this->assertEquals(1, $p->customerID);
    	$this->assertInstanceOf(DateTime::class, $p->placed);
    	$now = new DateTime();
    	$p->placed = $now;
    	$this->assertEquals($now->getTimestamp(), $p->placed->getTimestamp());
    }

    public function testJson () {
    	$db = $this->getConnection();
    	$p = new TypeTest($db);

    	$p->id = 1;
    	$data = [1 => 12];
    	$p->jsonField = $data;
    	$this->assertEquals($data, $p->jsonField, "just set");
    	$this->assertTrue($p->insert());

    	$p1 = new TypeTest($db);
    	$this->assertTrue($p1->fetch([1]));
    	$this->assertEquals($data, $p1->jsonField, "fetch");
    	$data[1] = 13;
    	$p1->jsonField = $data;
    	$this->assertTrue($p1->update());

    	$this->assertTrue($p->fetch([1]));
    	$this->assertEquals($data, $p->jsonField, "fetch 2");
    }

    // To cover all existing data entities for code coverage
    public function tableListProvider ()	{
    	$db = $this->getConnection();
    	$res = $db->query("SHOW TABLES");
    	$tables = [];
    	while ( $row = $res->fetch())	{
    		$tables[] = [ucfirst($row['Tables_in_whTest'])];
    	}
    	return $tables;
    }

    /**
     * @dataProvider tableListProvider
     */
     public function testCoverTables( $tableName )	{
    	$db = $this->getConnection();
    	$class = "\\wh\\data\\wh\\".$tableName;
		$test = new $class($db);
		$this->assertNotNull($test->fetchWhere('1=0', []));
    }

    public function testInsertBlock1 () {
    	$db = $this->getConnection();
    	$p = new Product($db);
    	$p->insertBlock(["id", "tag", "description"],
    			[[1, 'p1', 'product 1']]);

    	$this->assertTrue($p->fetch([1]));
    	$this->assertEquals(1, $p->id);
    	$this->assertEquals('p1', $p->tag);
    	$this->assertEquals('product 1', $p->description);
    }

    public function testInsertBlock2 () {
    	$db = $this->getConnection();
    	$p = new Product($db);
    	$p->insertBlock(["id", "tag", "description"],
    			[
    				[1, 'p1', 'product 1'],
    				[2, 'p2', 'product 2'],
    				[3, 'p3', 'product 3']
    			]);

    	$this->assertTrue($p->fetch([1]));
    	$this->assertEquals(1, $p->id);
    	$this->assertEquals('p1', $p->tag);
    	$this->assertEquals('product 1', $p->description);
    	$this->assertTrue($p->fetch([2]));
    	$this->assertEquals('product 2', $p->description);
    	$this->assertTrue($p->fetch([3]));
    	$this->assertEquals('product 3', $p->description);
    }
}