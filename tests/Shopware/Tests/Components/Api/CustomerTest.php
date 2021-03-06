<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

/**
 * @category  Shopware
 * @package   Shopware\Tests
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Tests_Components_Api_CustomerTest extends Enlight_Components_Test_TestCase
{
    /**
     * @var \Shopware\Components\Api\Resource\Customer
     */
    private $resource;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();

        Shopware()->Models()->clear();

        $this->resource = new \Shopware\Components\Api\Resource\Customer();
        $this->resource->setManager(Shopware()->Models());
    }

    protected function getAclMock()
    {
        $aclMock = $this->getMockBuilder('\Shopware_Components_Acl')
                ->disableOriginalConstructor()
                ->getMock();

        $aclMock->expects($this->any())
                ->method('has')
                ->will($this->returnValue(true));

        $aclMock->expects($this->any())
                ->method('isAllowed')
                ->will($this->returnValue(false));

        return $aclMock;
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\PrivilegeException
     */
    public function testGetOneWithMissinPrivilegeShouldThrowPrivilegeException()
    {
        $this->resource->setRole('dummy');
        $this->resource->setAcl($this->getAclMock());

        $this->resource->getOne(1);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\NotFoundException
     */
    public function testGetOneWithInvalidIdShouldThrowNotFoundException()
    {
        $this->resource->getOne(9999999);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function testGetOneWithMissingIdShouldThrowParameterMissingException()
    {
        $this->resource->getOne('');
    }

    /**
     * @expectedException Shopware\Components\Api\Exception\CustomValidationException
     * @expectedExceptionMessage Emailaddress test@example.com for shopId 1 is not unique
     */
    public function testCreateWithNonUniqueEmailShouldThrowException()
    {
        $testData = array(
            "password" => "fooobar",
            "active"   => true,
            "email"    => 'test@example.com',
        );

        $this->resource->create($testData);
    }

    public function testCreateShouldBeSuccessful()
    {
        $date = new DateTime();
        $date->modify('-10 days');
        $firstlogin = $date->format(DateTime::ISO8601);

        $date->modify('+2 day');
        $lastlogin = $date->format(DateTime::ISO8601);

        $birthday = DateTime::createFromFormat('Y-m-d', '1986-12-20')->format(DateTime::ISO8601);

        $testData = array(
            "password" => "fooobar",
            "email"    => uniqid() . 'test@foobar.com',

            "firstlogin" => $firstlogin,
            "lastlogin"  => $lastlogin,

            "billing" => array(
                "firstName" => "Max",
                "lastName"  => "Mustermann",
                "birthday"  => $birthday,
                "attribute" => array(
                    'text1' => 'Freitext1',
                    'text2' => 'Freitext2',
                ),
            ),

            "shipping" => array(
                "salutation" => "Mr",
                "company"    => "Widgets Inc.",
                "firstName"  => "Max",
                "lastName"   => "Mustermann",
                "attribute" => array(
                    'text1' => 'Freitext1',
                    'text2' => 'Freitext2',
                ),
            ),

            "debit" => array(
                "account"       => "Fake Account",
                "bankCode"      => "55555555",
                "bankName"      => "Fake Bank",
                "accountHolder" => "Max Mustermann",
            ),
        );

        $customer = $this->resource->create($testData);

        $this->assertInstanceOf('\Shopware\Models\Customer\Customer', $customer);
        $this->assertGreaterThan(0, $customer->getId());

        // Test default values
        $this->assertEquals($customer->getShop()->getId(), 1);
        $this->assertEquals($customer->getAccountMode(), 0);
        $this->assertEquals($customer->getGroup()->getKey(), "EK");
        $this->assertEquals($customer->getActive(), true);

        $this->assertEquals($customer->getEmail(), $testData['email']);
        $this->assertEquals($customer->getBilling()->getFirstName(), $testData['billing']['firstName']);
        $this->assertEquals($customer->getBilling()->getAttribute()->getText1(), $testData['billing']['attribute']['text1']);
        $this->assertEquals($customer->getShipping()->getFirstName(), $testData['shipping']['firstName']);
        $this->assertEquals($customer->getShipping()->getAttribute()->getText1(), $testData['shipping']['attribute']['text1']);

        return $customer->getId();
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetOneShouldBeSuccessful($id)
    {
        $customer = $this->resource->getOne($id);
        $this->assertGreaterThan(0, $customer['id']);
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetOneByNumberShouldBeSuccessful($id)
    {
        $this->resource->setResultMode(\Shopware\Components\Api\Resource\Resource::HYDRATE_OBJECT);
        $customer = $this->resource->getOne($id);
        $number = $customer->getBilling()->getNumber();

        $customer = $this->resource->getOneByNumber($number);
        $this->assertEquals($id, $customer->getId());
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetOneShouldBeAbleToReturnObject($id)
    {
        $this->resource->setResultMode(\Shopware\Components\Api\Resource\Resource::HYDRATE_OBJECT);
        $customer = $this->resource->getOne($id);

        $this->assertInstanceOf('\Shopware\Models\Customer\Customer', $customer);
        $this->assertGreaterThan(0, $customer->getId());
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetListShouldBeSuccessful()
    {
        $result = $this->resource->getList();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);

        $this->assertGreaterThanOrEqual(1, $result['total']);
        $this->assertGreaterThanOrEqual(1, $result['data']);
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testGetListShouldBeAbleToReturnObjects()
    {
        $this->resource->setResultMode(\Shopware\Components\Api\Resource\Resource::HYDRATE_OBJECT);
        $result = $this->resource->getList();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);

        $this->assertGreaterThanOrEqual(1, $result['total']);
        $this->assertGreaterThanOrEqual(1, $result['data']);

        $this->assertInstanceOf('\Shopware\Models\Customer\Customer', $result['data'][0]);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ValidationException
     */
    public function testCreateWithInvalidDataShouldThrowValidationException()
    {
        $testData = array(
            'active'  => true,
            'email'   => 'invalid',
            'billing' => array(
                'firstName' => 'Max',
                'lastName'  => 'Mustermann',
            ),
        );

        $this->resource->create($testData);
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testUpdateShouldBeSuccessful($id)
    {
        $testData = array(
            'active'  => true,
            'email'   => uniqid() . 'update@foobar.com',
            'billing' => array(
                'firstName' => 'Max Update',
                'lastName'  => 'Mustermann Update',
            ),
        );

        $customer = $this->resource->update($id, $testData);

        $this->assertInstanceOf('\Shopware\Models\Customer\Customer', $customer);
        $this->assertEquals($id, $customer->getId());

        $this->assertEquals($customer->getEmail(), $testData['email']);
        $this->assertEquals($customer->getBilling()->getFirstName(), $testData['billing']['firstName']);

        return $id;
    }

    /**
     * @depends testCreateShouldBeSuccessful
     */
    public function testUpdateByNumberShouldBeSuccessful($id)
    {
        $this->resource->setResultMode(\Shopware\Components\Api\Resource\Resource::HYDRATE_OBJECT);
        $customer = $this->resource->getOne($id);
        $number = $customer->getBilling()->getNumber();

        $testData = array(
            'active'  => true,
            'email'   => uniqid() . 'update@foobar.com',
            'billing' => array(
                'firstName' => 'Max Update',
                'lastName'  => 'Mustermann Update',
            ),
        );

        $customer = $this->resource->updateByNumber($number, $testData);

        $this->assertInstanceOf('\Shopware\Models\Customer\Customer', $customer);
        $this->assertEquals($id, $customer->getId());

        $this->assertEquals($customer->getEmail(), $testData['email']);
        $this->assertEquals($customer->getBilling()->getFirstName(), $testData['billing']['firstName']);

        return $number;
    }

    /**
     * @depends testCreateShouldBeSuccessful
     * @expectedException \Shopware\Components\Api\Exception\ValidationException
     */
    public function testUpdateWithInvalidDataShouldThrowValidationException($id)
    {
        $testData = array(
            'active'  => true,
            'email'   => 'invalid',
            'billing' => array(
                'firstName' => 'Max',
                'lastName'  => 'Mustermann',
            ),
        );

        $this->resource->update($id, $testData);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\NotFoundException
     */
    public function testUpdateWithInvalidIdShouldThrowNotFoundException()
    {
        $this->resource->update(9999999, array());
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function testUpdateWithMissingIdShouldThrowParameterMissingException()
    {
        $this->resource->update('', array());
    }

    /**
     * @depends testUpdateShouldBeSuccessful
     */
    public function testDeleteShouldBeSuccessful($id)
    {
        $customer = $this->resource->delete($id);

        $this->assertInstanceOf('\Shopware\Models\Customer\Customer', $customer);
        $this->assertEquals(null, $customer->getId());
        $this->assertEquals(null, $customer->getShipping()->getId());
        $this->assertEquals(null, $customer->getBilling()->getId());
        $this->assertEquals(null, $customer->getDebit()->getId());
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\NotFoundException
     */
    public function testDeleteWithInvalidIdShouldThrowNotFoundException()
    {
        $this->resource->delete(9999999);
    }

    /**
     * @expectedException \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function testDeleteWithMissingIdShouldThrowParameterMissingException()
    {
        $this->resource->delete('');
    }
}
