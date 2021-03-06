<?php

namespace CommerceGuys\Tax\Tests\Resolver;

use CommerceGuys\Addressing\Model\AddressInterface;
use CommerceGuys\Tax\Repository\TaxTypeRepository;
use CommerceGuys\Tax\Resolver\TaxType\EuTaxTypeResolver;
use org\bovigo\vfs\vfsStream;

/**
 * @coversDefaultClass \CommerceGuys\Tax\Resolver\TaxType\EuTaxTypeResolver
 */
class EuTaxTypeResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Known tax types.
     *
     * @var array
     */
    protected $taxTypes = array(
        'fr_vat' => array(
            'name' => 'French VAT',
            'zone' => 'fr_vat',
            'tag' => 'EU',
            'rates' => array(
                array(
                    'id' => 'fr_vat_standard',
                    'name' => 'Standard',
                    'display_name' => '% VAT',
                    'default' => true,
                    'amounts' => array(
                        array(
                            'id' => 'fr_vat_standard_196',
                            'amount' => 0.196,
                            'start_date' => '2004-04-01',
                            'end_date' => '2013-12-31',
                        ),
                        array(
                            'id' => 'fr_vat_standard_20',
                            'amount' => 0.2,
                            'start_date' => '2014-01-01',
                        ),
                    ),
                ),
            ),
        ),
        'de_vat' => array(
            'name' => 'German VAT',
            'zone' => 'de_vat',
            'tag' => 'EU',
            'rates' => array(
                array(
                    'id' => 'de_vat_standard',
                    'name' => 'Standard',
                    'display_name' => '% VAT',
                    'default' => true,
                    'amounts' => array(
                        array(
                            'id' => 'de_vat_standard_19',
                            'amount' => 0.19,
                            'start_date' => '2007-01-01',
                        ),
                    ),
                ),
            ),
        ),
        'eu_ic_vat' => array(
            'name' => 'Intra-Community Supply',
            'zone' => 'eu_vat',
            'tag' => 'EU',
            'rates' => array(
                array(
                    'id' => 'eu_ic_vat',
                    'name' => 'Intra-Community Supply',
                    'display_name' => '% VAT',
                    'default' => true,
                    'amounts' => array(
                        array(
                            'id' => 'eu_ic_vat',
                            'amount' => 0,
                        ),
                    ),
                ),
            ),
        ),
    );

    /**
     * Known zones.
     *
     * Note: The real fr_vat and de_vat zones are more complex, France excludes
     * Corsica, Germany excludes Heligoland and Bussingen, but includes 4
     * Austrian postal codes. Those details were irrelevant for this test.
     *
     * @var array
     */
    protected $zones = array(
        'fr_vat' => array(
            'name' => 'France (VAT)',
            'members' => array(
                array(
                    'type' => 'country',
                    'id' => '1',
                    'name' => 'France',
                    'country_code' => 'FR',
                ),
                array(
                    'type' => 'country',
                    'id' => '2',
                    'name' => 'Monaco',
                    'country_code' => 'MC',
                ),
            ),
        ),
        'de_vat' => array(
            'name' => 'Germany (VAT)',
            'members' => array(
                array(
                    'type' => 'country',
                    'id' => '2',
                    'name' => 'Germany',
                    'country_code' => 'DE',
                ),
            ),
        ),
        'eu_vat' => array(
            'name' => 'European Union (VAT)',
            'members' => array(
                array(
                    'type' => 'zone',
                    'id' => '3',
                    'name' => 'France (VAT)',
                    'zone' => 'fr_vat',
                ),
                array(
                    'type' => 'zone',
                    'id' => '4',
                    'name' => 'Germany (VAT)',
                    'zone' => 'de_vat',
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
        $resolver = new EuTaxTypeResolver($taxTypeRepository);
        $this->assertSame($taxTypeRepository, $this->getObjectAttribute($resolver, 'taxTypeRepository'));

        return $resolver;
    }

    /**
     * @covers ::resolve
     * @covers ::filterByAddress
     * @covers ::getTaxTypes
     * @covers \CommerceGuys\Tax\Resolver\TaxType\StoreRegistrationCheckerTrait
     * @uses \CommerceGuys\Tax\Repository\TaxTypeRepository
     * @uses \CommerceGuys\Tax\Model\TaxType
     * @uses \CommerceGuys\Tax\Model\TaxRate
     * @uses \CommerceGuys\Tax\Model\TaxRateAmount
     * @depends testConstructor
     * @dataProvider dataProvider
     */
    public function testResolver($taxable, $context, $expected, $resolver)
    {
        $results = $resolver->resolve($taxable, $context);
        if (empty($expected) || $expected == EuTaxTypeResolver::NO_APPLICABLE_TAX_TYPE) {
            $this->assertEquals($expected, $results);
        }
        else {
            $result = reset($results);
            $this->assertInstanceOf('CommerceGuys\Tax\Model\TaxType', $result);
            $this->assertEquals($expected, $result->getId());
        }
    }

    /**
     * Provides data for the resolver test.
     */
    public function dataProvider()
    {
        $mockTaxableBuilder = $this->getMockBuilder('CommerceGuys\Tax\TaxableInterface');
        $physicalTaxable = $mockTaxableBuilder->getMock();
        $physicalTaxable->expects($this->any())
            ->method('isPhysical')
            ->will($this->returnValue(true));
        $digitalTaxable = $mockTaxableBuilder->getMock();

        $mockAddressBuilder = $this->getMockBuilder('CommerceGuys\Addressing\Model\Address');
        $serbianAddress = $mockAddressBuilder->getMock();
        $serbianAddress->expects($this->any())
            ->method('getCountryCode')
            ->will($this->returnValue('RS'));
        $frenchAddress = $mockAddressBuilder->getMock();
        $frenchAddress->expects($this->any())
            ->method('getCountryCode')
            ->will($this->returnValue('FR'));
        $germanAddress = $mockAddressBuilder->getMock();
        $germanAddress->expects($this->any())
            ->method('getCountryCode')
            ->will($this->returnValue('DE'));
        $usAddress = $mockAddressBuilder->getMock();
        $usAddress->expects($this->any())
            ->method('getCountryCode')
            ->will($this->returnValue('US'));

        $date1 = new \DateTime('2014-02-24');
        $date2 = new \DateTime('2015-02-24');
        $notApplicable = EuTaxTypeResolver::NO_APPLICABLE_TAX_TYPE;

        return array(
            // German customer, French store, VAT number provided.
            array($physicalTaxable, $this->getContext($germanAddress, $frenchAddress, '123'), 'eu_ic_vat'),
            // German customer, French store, physical product.
            array($physicalTaxable, $this->getContext($germanAddress, $frenchAddress), 'fr_vat'),
            // German customer, French store registered for German VAT, physical product.
            array($physicalTaxable, $this->getContext($germanAddress, $frenchAddress, '', array('DE')), 'de_vat'),
            // German customer, French store, digital product before Jan 1st 2015.
            array($digitalTaxable, $this->getContext($germanAddress, $frenchAddress, '', array(), $date1), 'fr_vat'),
            // German customer, French store, digital product.
            array($digitalTaxable, $this->getContext($germanAddress, $frenchAddress, '', array(), $date2), 'de_vat'),
            // German customer, US store, digital product
            array($digitalTaxable, $this->getContext($germanAddress, $usAddress, '', array(), $date2), array()),
            // German customer, US store registered in FR, digital product.
            array($digitalTaxable, $this->getContext($germanAddress, $usAddress, '', array('FR'), $date2), 'de_vat'),
            // German customer with VAT number, US store registered in FR, digital product.
            array($digitalTaxable, $this->getContext($germanAddress, $usAddress, '123', array('FR'), $date2), $notApplicable),
            // Serbian customer, French store, physical product.
            array($physicalTaxable, $this->getContext($serbianAddress, $frenchAddress), array()),
            // French customer, Serbian store, physical product.
            array($physicalTaxable, $this->getContext($frenchAddress, $serbianAddress), array()),
        );
    }

    /**
     * Returns a mock context based on the provided data.
     *
     * @param AddressInterface $customerAddress        The customer address.
     * @param AddressInterface $storeAddress           The store address.
     * @param string           $customerTaxNumber      The customer tax number.
     * @param array            $storeRegistrations Additional tax countries.
     * @param \DateTime        $date                   The date.
     *
     * @return \CommerceGuys\Tax\Resolver\Context
     */
    protected function getContext($customerAddress, $storeAddress, $customerTaxNumber = '', $storeRegistrations = array(), $date = null)
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
            ->method('getCustomerTaxNumber')
            ->will($this->returnValue($customerTaxNumber));
        $context->expects($this->any())
            ->method('getStoreRegistrations')
            ->will($this->returnValue($storeRegistrations));
        $date = $date ?: new \DateTime();
        $context->expects($this->any())
            ->method('getDate')
            ->will($this->returnValue($date));

        return $context;
    }
}
