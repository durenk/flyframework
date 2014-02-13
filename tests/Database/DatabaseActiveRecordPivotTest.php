<?php

use Mockery as m;
use Fly\Database\ActiveRecord\Relations\Pivot;

class DatabaseActiveRecordPivotTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}


	public function testPropertiesAreSetCorrectly()
	{
		$parent = m::mock('Fly\Database\ActiveRecord\Model[getConnectionName]');
		$parent->shouldReceive('getConnectionName')->once()->andReturn('connection');
		$pivot = new Pivot($parent, array('foo' => 'bar'), 'table', true);

		$this->assertEquals(array('foo' => 'bar'), $pivot->getAttributes());
		$this->assertEquals('connection', $pivot->getConnectionName());
		$this->assertEquals('table', $pivot->getTable());
		$this->assertTrue($pivot->exists);
	}


	public function testTimestampPropertyIsSetIfCreatedAtInAttributes()
	{
		$parent = m::mock('Fly\Database\ActiveRecord\Model[getConnectionName,getDates]');
		$parent->shouldReceive('getConnectionName')->andReturn('connection');
		$parent->shouldReceive('getDates')->andReturn(array());
		$pivot = new DatabaseActiveRecordPivotTestDateStub($parent, 
			array('foo' => 'bar', 'created_at' => 'foo'), 'table');
		$this->assertTrue($pivot->timestamps);

		$pivot = new DatabaseActiveRecordPivotTestDateStub($parent, array('foo' => 'bar'), 'table');
		$this->assertFalse($pivot->timestamps);
	}


	public function testKeysCanBeSetProperly()
	{
		$parent = m::mock('Fly\Database\ActiveRecord\Model[getConnectionName]');
		$parent->shouldReceive('getConnectionName')->once()->andReturn('connection');
		$pivot = new Pivot($parent, array('foo' => 'bar'), 'table');
		$pivot->setPivotKeys('foreign', 'other');

		$this->assertEquals('foreign', $pivot->getForeignKey());
		$this->assertEquals('other', $pivot->getOtherKey());
	}


	public function testDeleteMethodDeletesModelByKeys()
	{
		$parent = m::mock('Fly\Database\ActiveRecord\Model[getConnectionName]');
		$parent->guard(array());
		$parent->shouldReceive('getConnectionName')->once()->andReturn('connection');
		$pivot = $this->getMock('Fly\Database\ActiveRecord\Relations\Pivot', 
			array('newQuery'), array($parent, array('foo' => 'bar'), 'table'));
		$pivot->setPivotKeys('foreign', 'other');
		$pivot->foreign = 'foreign.value';
		$pivot->other = 'other.value';
		$query = m::mock('stdClass');
		$query->shouldReceive('where')->once()->with('foreign', 'foreign.value')->andReturn($query);
		$query->shouldReceive('where')->once()->with('other', 'other.value')->andReturn($query);
		$query->shouldReceive('delete')->once()->andReturn(true);
		$pivot->expects($this->once())->method('newQuery')->will($this->returnValue($query));

		$this->assertTrue($pivot->delete());
	}

}


class DatabaseActiveRecordPivotTestModelStub extends Fly\Database\ActiveRecord\Model {}

class DatabaseActiveRecordPivotTestDateStub extends Fly\Database\ActiveRecord\Relations\Pivot {
	public function getDates()
	{
		return array();
	}
}