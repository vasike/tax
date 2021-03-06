<?php

namespace CommerceGuys\Tax\Tests\Resolver;

use CommerceGuys\Addressing\Model\AddressInterface;
use CommerceGuys\Tax\Repository\TaxTypeRepository;
use CommerceGuys\Tax\Resolver\TaxType\CanadaTaxTypeResolver;
use org\bovigo\vfs\vfsStream;

/**
 * @coversDefaultClass \CommerceGuys\Tax\Resolver\TaxType\CanadaTaxTypeResolver
 */
class CanadaTaxTypeResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Known tax types.
     *
     * @var array
     */
    protected $taxTypes = array(
        'ca_on_hst' => array(
            'name' => 'Ontario HST',
            'tag' => 'CA',
            'zone' => 'ca_on_hst',
            'rates' => array(
                array(
                    'id' => 'ca_on_hst',
                    'name' => 'Ontario HST',
                    'display_name' => '% HST',
                    'amounts' => array(
                        array(
                            'id' => 'ca_on_hst_13',
                            'amount' => 0.13,
                            'start_date' => '2010-07-01',
                        ),
                    ),
                ),
            ),
        ),
        'ca_ns_hst' => array(
            'name' => 'Nova Scotia HST',
            'tag' => 'CA',
            'zone' => 'ca_ns_hst',
            'rates' => array(
                array(
                    'id' => 'ca_ns_hst',
                    'name' => 'Nova Scotia HST',
                    'display_name' => '% HST',
                    'amounts' => array(
                        array(
                            'id' => 'ca_ns_hst_15',
                            'amount' => 0.15,
                            'start_date' => '2010-07-01',
                        ),
                    ),
                ),
            ),
        ),
    );

    /**
     * Known zones.
     *
     * @var array
     */
    protected $zones = array(
        'ca_on_hst' => array(
            'name' => 'Ontario (HST)',
            'members' => array(
                array(
                    'type' => 'country',
                    'id' => '1',
                    'name' => 'Canada - Ontario',
                    'country_code' => 'CA',
                    'administrative_area' => 'CA-ON',
                ),
            ),
        ),
        'ca_ns_hst' => array(
            'name' => 'Nova Scotia (HST)',
            'members' => array(
                array(
                    'type' => 'country',
                    'id' => '2',
                    'name' => 'Canada - Nova Scotia',
                    'country_code' => 'CA',
                    'administrative_area' => 'CA-NS',
                ),
            ),
        ),
    );

    /**
     * @covers ::__construct
     * @uses \CommerceGuys\Tax\Repository\TaxTypeRepository
     */
    public function testConstructor()
    {
        $root = vfsStream::setup('resources');
        $directory = vfsStream::newDirectory('tax_type')->at($root);
        foreach ($this->taxTypes as $id => $definition) {
            $filename = $id . '.json';
            vfsStream::newFile($filename)->at($directory)->setContent(json_encode($definition));
        }
        $directory = vfsStream::newDirectory('zone')->at($root);
        foreach ($this->zones as $id => $definition) {
            $filename = $id . '.json';
            vfsStream::newFile($filename)->at($directory)->setContent(json_encode($definition));
        }

        $taxTypeRepository = new TaxTypeRepository('vfs://resources/');
        $resolver = new CanadaTaxTypeResolver($taxTypeRepository);
        $this->assertSame($taxTypeRepository, $this->getObjectAttribute($resolver, 'taxTypeRepository'));

        return $resolver;
    }

    /**
     * @covers ::resolve
     * @covers ::getTaxTypes
     * @uses \CommerceGuys\Tax\Resolver\TaxType\StoreRegistrationCheckerTrait
     * @uses \CommerceGuys\Tax\Repository\TaxTypeRepository
     * @uses \CommerceGuys\Tax\Model\TaxType
     * @uses \CommerceGuys\Tax\Model\TaxRate
     * @uses \CommerceGuys\Tax\Model\TaxRateAmount
     * @depends testConstructor
     */
    public function testResolver($resolver)
    {
        $taxable = $this
            ->getMockBuilder('CommerceGuys\Tax\TaxableInterface')
            ->getMock();
        $usAddress = $this
            ->getMockBuilder('CommerceGuys\Addressing\Model\Address')
            ->getMock();
        $usAddress->expects($this->any())
            ->method('getCountryCode')
            ->will($this->returnValue('US'));
        $ontarioAddress = $this
            ->getMockBuilder('CommerceGuys\Addressing\Model\Address')
            ->getMock();
        $ontarioAddress->expects($this->any())
            ->method('getCountryCode')
            ->will($this->returnValue('CA'));
        $ontarioAddress->expects($this->any())
            ->method('getAdministrativeArea')
            ->will($this->returnValue('CA-ON'));
        $novaScotiaAddress = $this
            ->getMockBuilder('CommerceGuys\Addressing\Model\Address')
            ->getMock();
        $novaScotiaAddress->expects($this->any())
            ->method('getCountryCode')
            ->will($this->returnValue('CA'));
        $novaScotiaAddress->expects($this->any())
            ->method('getAdministrativeArea')
            ->will($this->returnValue('CA-NS'));

        // Nova Scotia store, Ontario customer.
        $context = $this->getContext($ontarioAddress, $novaScotiaAddress);
        $results = $resolver->resolve($taxable, $context);
        $result = reset($results);
        $this->assertInstanceOf('CommerceGuys\Tax\Model\TaxType', $result);
        $this->assertEquals('ca_on_hst', $result->getId());

        // Ontario store, Nova Scotia customer.
        $context = $this->getContext($novaScotiaAddress, $ontarioAddress);
        $results = $resolver->resolve($taxable, $context);
        $result = reset($results);
        $this->assertInstanceOf('CommerceGuys\Tax\Model\TaxType', $result);
        $this->assertEquals('ca_ns_hst', $result->getId());

        // Ontario store, US customer.
        $context = $this->getContext($usAddress, $ontarioAddress);
        $result = $resolver->resolve($taxable, $context);
        $this->assertEquals(array(), $result);

        // US store, Ontario customer.
        $context = $this->getContext($ontarioAddress, $usAddress);
        $result = $resolver->resolve($taxable, $context);
        $this->assertEquals(array(), $result);
    }

    /**
     * Returns a mock context based on the provided data.
     *
     * @param AddressInterface $customerAddress        The customer address.
     * @param AddressInterface $storeAddress           The store address.
     * @param array            $storeRegistrations Additional tax countries.
     *
     * @return \CommerceGuys\Tax\Resolver\Context
     */
    protected function getContext($customerAddress, $storeAddress, $storeRegistrations = array())
    {
        $context = $this
            ->getMockBuilder('CommerceGuys\Tax\Resolver\Context')
            ->disableOriginalConstructor()
            ->getMock();
        $context->expects($this->any())
            ->method('getCustomerAddress')
            ->will($this->returnValue($customerAddress));
        $context->expects($this->any())
            ->method('getStoreAddress')
            ->will($this->returnValue($storeAddress));
        $context->expects($this->any())
            ->method('getStoreRegistrations')
            ->will($this->returnValue($storeRegistrations));

        return $context;
    }
}
