<?php
/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, https://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */
use App\Test\TestCase\AppCakeTestCase;
use App\Lib\Csv\BankingReader;
use App\Model\Entity\Customer;
use Cake\Core\Configure;

class BankingReaderTest extends AppCakeTestCase
{
    
    public function setUp(): void
    {
        parent::setUp();
    }
    
    public function tearDown(): void
    {
        $this->assertLogFilesForErrors();
    }
    
    public function testReadRaiffeisen()
    {
        $reader = BankingReader::createFromPath(TESTS . 'config' . DS . 'data' . DS . 'test-data-raiffeisen.csv');
        $records = $reader->getPreparedRecords($reader->getRecords());
        foreach($records as $record) {
            $this->assertEquals(5, count($record));
        }

        $this->assertEquals('2019-02-01 12:51:14.563000', $records[0]['date']);
        $this->assertEquals(100, $records[0]['amount']);
        $this->assertTrue(Customer::class == get_class($records[0]['original_customer']));
        $this->assertTrue(Customer::class == get_class($records[1]['original_customer']));
        $this->assertEquals(Configure::read('test.adminId'), $records[0]['original_customer']['id_customer']);
        $this->assertEquals(Configure::read('test.superadminId'), $records[1]['original_customer']['id_customer']);
        $this->assertEquals(Configure::read('test.adminId'), $records[0]['original_id_customer']);
        $this->assertEquals(Configure::read('test.superadminId'), $records[1]['original_id_customer']);
        $this->assertNull($records[2]['original_customer']);
        
        $this->assertEquals(3, count($records));
    }
    
}
