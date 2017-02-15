<?php

namespace Olcs\TestHelpers\FormTester;

use Common\Form\Element\DynamicSelect;
use Zend\Validator;

/**
 * Class AbstractFormValidationTest
 */
abstract class AbstractFormValidationTestCase extends \Mockery\Adapter\Phpunit\MockeryTestCase
{
    /**
     * @var string The class name of the form being tested
     */
    protected $formName = \Olcs\Form\Model\Form\NonPi::class;
    /**
     * @var \Common\Form\Form
     */
    protected $sut;

    /**
     * If you intentionally want to skip tests on an element they can be added here
     * @var array List of form elements eg (fields.numOfCows) that have been tested
     */
    static private $testedElements = [];


    protected function getServiceManager()
    {
        return \OlcsTest\Bootstrap::getRealServiceManager();
    }

    protected function setUp()
    {
        $serviceManager = $this->getServiceManager();
        $serviceManager->setAllowOverride(true);

        $serviceManager->get('FormElementManager')->setFactory(
            'DynamicSelect',
            function ($serviceLocator, $name, $requestedName) {
                $element = new DynamicSelect();
                $element->setValueOptions(['1' => 'one', '2' => 'two', '3' => 'three']);
                return $element;
            }
        );

        $this->sut = $serviceManager->get('FormAnnotationBuilder')->createForm($this->formName);
    }

    /**
     * Get the form object
     *
     * @return \Common\Form\Form
     */
    protected function getForm()
    {
        $serviceManager = $this->getServiceManager();
        $serviceManager->setAllowOverride(true);

        $serviceManager->get('FormElementManager')->setFactory(
            'DynamicSelect',
            function ($serviceLocator, $name, $requestedName) {
                $element = new DynamicSelect();
                $element->setValueOptions(['1' => 'one', '2' => 'two', '3' => 'three']);
                return $element;
            }
        );

        return $serviceManager->get('FormAnnotationBuilder')->createForm($this->formName);
//            foreach ($this->getDynamicSelectData() as $dyanamicData) {
//                list($stack, $data) = $dyanamicData;
//
//                $element = $this->form;
//
//                foreach ($stack as $name) {
//                    $element = $element->get($name);
//                }
//
//                $element->setValueOptions($data);
//            }
    }

    /**
     * Assert that a form element with a value is valid
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     * @param mixed  $value           The value to be tested in the form element
     * @param array  $context         Form data context required to test the validation
     *
     * @return void
     */
    protected function assertFormElementValid(array $elementHierarchy, $value, array $context = [])
    {
        self::$testedElements[implode($elementHierarchy, '.')] = true;

        $this->setData($elementHierarchy, $value, $context);
        $this->setValidationGroup($elementHierarchy);

        $valid = $this->sut->isValid();
        $message = sprintf(
            '"%s" form element with value "%s" should be valid : %s',
            implode($elementHierarchy, '.'),
            print_r($value, true),
            implode(array_keys($this->getFormMessages($elementHierarchy)), ', ')
        );

        $this->assertTrue($valid, $message);
    }

    /**
     * Get the form validation messages for an element
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return array
     */
    protected function getFormMessages(array $elementHierarchy)
    {
        $messages = $this->sut->getMessages();
        foreach ($elementHierarchy as $name) {
            if (isset($messages[$name])) {
                $messages = $messages[$name];
            }
        }
        return $messages;
    }

    /**
     * Set the validation group so that ony the form element is validated
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     */
    protected function setValidationGroup(array $elementHierarchy)
    {
        $array = null;
        foreach (array_reverse($elementHierarchy) as $name) {
            if ($array == null) {
                $array = [$name];
            } else {
                $array = [$name => $array];
            }
        }

        $this->sut->setValidationGroup($array);
    }

    /**
     * Set the form data
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     * @param mixed $value            Form element (being tested) value
     * @param array  $context         Form data context required to test the validation
     */
    protected function setData(array $elementHierarchy, $value, $context = [])
    {
        $array = $value;
        foreach (array_reverse($elementHierarchy) as $name) {
            $array = [$name => $array];
        }

        $this->sut->setData(array_merge($context, $array));
    }

    /**
     * Assert that a form element with a value is NOT valid
     *
     * @param array        $elementHierarchy   Form element name eg ['fields','numOfCows']
     * @param mixed        $value              The value to be tested in the form element
     * @param string|array $validationMessages A single or an array of expected validation messages keys
     * @param array        $context            Form data context required to test the validation
     */
    protected function assertFormElementNotValid(array $elementHierarchy, $value, $validationMessages, $context = [])
    {
        self::$testedElements[implode($elementHierarchy, '.')] = true;

        if (!is_array($validationMessages)) {
            $validationMessages = [$validationMessages];
        }

        $this->setData($elementHierarchy, $value, $context);
        $this->setValidationGroup($elementHierarchy);

        $valid = $this->sut->isValid();

        $this->assertFalse(
            $valid,
            sprintf(
                '"%s" form element with value "%s" should *not* be valid',
                implode($elementHierarchy, '.'),
                print_r($value, true)
            )
        );
        $this->assertSame(
            $validationMessages,
            array_keys($this->getFormMessages($elementHierarchy)),
            sprintf(
                '"%s" form element with value "%s" error messages not as expected',
                implode($elementHierarchy, '.'),
                print_r($value, true))
        );
    }

    /**
     * Assert than a form element is a text input
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     * @param int   $min              Minimum allowed string length
     * @param int   $max              Maximum allowed string length
     *
     * @return void
     */
    protected function assertFormElementText($elementHierarchy, $min, $max)
    {
        $this->assertFormElementValid($elementHierarchy, str_pad('', $min, 'x'));
        $this->assertFormElementValid($elementHierarchy, str_pad('', $max, 'x'));
        if ($min > 0) {
            $this->assertFormElementNotValid($elementHierarchy, str_pad('', $max - 1, 'x'),
                Validator\StringLength::TOO_SHORT);
        }
        $this->assertFormElementNotValid($elementHierarchy, str_pad('', $max + 1, 'x'), Validator\StringLength::TOO_LONG);
    }

    /**
     * Assert than a form element is a number input
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     * @param int   $min              Minimum allowed value
     * @param int   $max              Maximum allowed value
     *
     * @return void
     */
    protected function assertFormElementNumber($elementHierarchy, $min, $max)
    {
        $this->assertFormElementValid($elementHierarchy, $min);
        $this->assertFormElementValid($elementHierarchy, $max);
        if ($min > 0) {
            $this->assertFormElementNotValid($elementHierarchy, $min - 1, Validator\Between::NOT_BETWEEN);
        }
        $this->assertFormElementNotValid($elementHierarchy, $max + 1, Validator\Between::NOT_BETWEEN);
        // @todo if min value is set then this test adds an extra validation message
        $this->assertFormElementNotValid($elementHierarchy, 'X', [Validator\Digits::STRING_EMPTY]);
    }

    /**
     * Assert that a form element is a number input
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     * @param bool  $required         Is the form element required
     *
     * @return void
     */
    protected function assertFormElementDynamicSelect($elementHierarchy, $required = true)
    {
        $this->assertFormElementValid($elementHierarchy, 1);
        $this->assertFormElementValid($elementHierarchy, '1');
        if ($required) {
            $this->assertFormElementNotValid($elementHierarchy, 'X', Validator\InArray::NOT_IN_ARRAY);
        }
    }

    /**
     * Assert that a form element is a date input
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementDate($elementHierarchy)
    {
        $errorMessages = [
            \Common\Validator\Date::DATE_ERR_CONTAINS_STRING,
            \Zend\Validator\Date::INVALID_DATE
        ];

        $this->assertFormElementValid($elementHierarchy, ['day' => 1, 'month' => '2', 'year' => 1999]);
        $this->assertFormElementNotValid($elementHierarchy, ['day' => 'X', 'month' => '2', 'year' => 1999], $errorMessages);
        $this->assertFormElementNotValid($elementHierarchy, ['day' => '1', 'month' => 'X', 'year' => 1999], $errorMessages);
        $this->assertFormElementNotValid(
            $elementHierarchy,
            ['day' => 1, 'month' => 3, 'year' => 'XXXX'],
            [
                \Common\Validator\Date::DATE_ERR_CONTAINS_STRING,
                \Common\Validator\Date::DATE_ERR_YEAR_LENGTH,
                Validator\Date::INVALID_DATE
            ]
        );
    }

    /**
     * Assert that a form element is a date time input
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementDateTime($elementHierarchy)
    {
        // @todo Needs to create this method, similar to this:
//        $errorMessages = [
//            \Common\Validator\Date::DATE_ERR_CONTAINS_STRING,
//            \Zend\Validator\Date::INVALID_DATE
//        ];
//
//        $this->assertFormElementValid($elementHierarchy, ['day' => 1, 'month' => '2', 'year' => 1999]);
//        $this->assertFormElementNotValid($elementHierarchy, ['day' => 'X', 'month' => '2', 'year' => 1999], $errorMessages);
//        $this->assertFormElementNotValid($elementHierarchy, ['day' => '1', 'month' => 'X', 'year' => 1999], $errorMessages);
//        $this->assertFormElementNotValid(
//            $elementHierarchy,
//            ['day' => 1, 'month' => 3, 'year' => 'XXXX'],
//            [
//                \Common\Validator\Date::DATE_ERR_CONTAINS_STRING,
//                \Common\Validator\Date::DATE_ERR_YEAR_LENGTH,
//                Validator\Date::INVALID_DATE
//            ]
//        );
    }

    /**
     * Assert whether a form element allows empty
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     * @param bool  $allowEmpty       if true, form element allows empty
     *
     * @return void
     */
    protected function assertFormElementAllowEmpty($elementHierarchy, $allowEmpty)
    {
        if ($allowEmpty === true) {
            $this->assertFormElementValid($elementHierarchy, '');
        } else {
            $this->assertFormElementNotValid($elementHierarchy, '', 'isEmpty');
        }
    }

    /**
     * Assert whether a form element is required
     *
     * @param string $elementHierarchy Form element name
     * @param bool $required true, form element is required
     *
     * @return void
     */
    protected function assertFormElementRequired($elementHierarchy, $required)
    {
        if ($required === true) {
            $this->assertFormElementNotValid($elementHierarchy, null, 'isEmpty');
        } else {
            $this->assertFormElementValid($elementHierarchy, null);
        }
    }

    /**
     * @todo How do we make this the last tests that runs
     *
     * Check that tests exists for all form elements
     *
     * @dataProvider dataProviderAllElementNames
     *
     * @param string $elementName Element name to test
     */
    public function testMissingTest($elementName)
    {
        if (!array_key_exists($elementName, self::$testedElements)) {
            $this->markTestIncomplete(sprintf('"%s" form element not tested', $elementName));
        }
    }

    /**
     * Data provider, a full ist of element names on this form
     *
     * @return array
     */
    public function dataProviderAllElementNames()
    {
        $elementList = $this->getElementList($this->getForm());
        foreach ($elementList as &$elementName) {
            $elementName = [$elementName];
        }
        return $elementList;
    }

    /**
     * Get a list of all form elements
     *
     * @param \Zend\Form\Fieldset $fieldsset
     * @param string              $prefix
     *
     * @return array eg ['fields.numOfCows', 'fields.numOfDogs']
     */
    private function getElementList(\Zend\Form\Fieldset $fieldsset, $prefix = '')
    {
        $elementList = [];
        /** @var \Zend\Form\Element $element */
        foreach ($fieldsset->getFieldsets() as $childFieldSet) {
            $elementList = array_merge($elementList, $this->getElementList($childFieldSet, $prefix . $childFieldSet->getName() .'.'));
        }
        foreach ($fieldsset->getElements() as $element) {
            $elementList[] = $prefix . $element->getName();
        }
        return $elementList;
    }
}