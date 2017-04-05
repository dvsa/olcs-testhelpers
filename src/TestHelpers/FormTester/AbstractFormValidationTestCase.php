<?php
namespace Olcs\TestHelpers\FormTester;

use Common\Form\Element\DynamicMultiCheckbox;
use Common\Form\Element\DynamicRadio;
use Common\Form\Element\DynamicSelect;
use Dvsa\Olcs\Transfer\Validators as TransferValidator;
use Mockery as m;
use Zend\Validator;

/**
 * Class AbstractFormValidationTest
 */
abstract class AbstractFormValidationTestCase extends \Mockery\Adapter\Phpunit\MockeryTestCase
{
    /**
     * @var string The class name of the form being tested
     */
    protected $formName;
    /**
     * @var \Common\Form\Form
     */
    protected $sut;

    /**
     * If you intentionally want to skip tests on an element they can be added here
     * @var array List of form elements eg (fields.numOfCows) that have been tested
     */
    static protected $testedElements = [];

    /**
     * @var \Zend\ServiceManager\ServiceLocatorInterface
     */
    protected $serviceManager;

    /**
     * @var array
     */
    private static $forms = [];

    /**
     * @throws \Exception
     *
     * @return void
     */
    protected function setUp()
    {
        // sut is not needed for the 'testMissingTest' tests, and it slows it down a lot
        if (strpos($this->getName(), 'testMissingTest') === false) {
            $this->sut = $this->getForm();
        }
    }

    /**
     * We can access service manager if we need to add a mock for certain applications
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    protected function getServiceManager()
    {
        if ($this->serviceManager !== null) {
            return $this->serviceManager;
        }

        if (class_exists('\OlcsTest\Bootstrap')) {
            $this->serviceManager = \OlcsTest\Bootstrap::getRealServiceManager();
        } elseif (class_exists('\CommonTest\Bootstrap')) {
            $this->serviceManager = \CommonTest\Bootstrap::getRealServiceManager();
        } else {
            throw new \Exception('Cannot find Bootstap');
        }

        $this->serviceManager->setAllowOverride(true);

        $this->serviceManager->get('FormElementManager')->setFactory(
            'DynamicSelect',
            function ($serviceLocator, $name, $requestedName) {
                $element = new DynamicSelect();
                $element->setValueOptions(['1' => 'one', '2' => 'two', '3' => 'three']);
                return $element;
            }
        );

        $this->serviceManager->get('FormElementManager')->setFactory(
            'DynamicRadio',
            function ($serviceLocator, $name, $requestedName) {
                $element = new DynamicRadio();
                $element->setValueOptions(['1' => 'one', '2' => 'two', '3' => 'three']);
                return $element;
            }
        );

        $this->serviceManager->setFactory(
            'Common\Form\Element\DynamicMultiCheckbox',
            function ($serviceLocator, $name, $requestedName) {
                $element = new DynamicMultiCheckbox();
                $element->setValueOptions(['1' => 'one', '2' => 'two', '3' => 'three']);
                return $element;
            }
        );

        return $this->serviceManager;
    }

    /**
     * Get the form object
     *
     * @return \Common\Form\Form
     */
    protected function getForm()
    {
        if ($this->formName == null) {
            throw new \Exception('formName property is not defined');
        }

        if (!isset(self::$forms[$this->formName])) {
            /** @var \Common\Form\Annotation\CustomAnnotationBuilder $c */
            $frmAnnotBuilder = $this->getServiceManager()->get('FormAnnotationBuilder');

            self::$forms[$this->formName] = $frmAnnotBuilder->createForm($this->formName);
        }

        return clone self::$forms[$this->formName];
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

        $this->assertElementExists($elementHierarchy);
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
        $this->sut->setData(array_merge_recursive($context, $array));
    }

    /**
     * Assert that the form element exists in the form
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     */
    protected function assertElementExists(array $elementHierarchy)
    {
        try {
            $this->getFormElement($elementHierarchy);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    /**
     * Get the form element
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return \Zend\Form\Element
     */
    protected function getFormElement(array $elementHierarchy)
    {
        $element = $this->sut;
        foreach ($elementHierarchy as $name) {
            if (!$element->has($name)) {
                throw new \Exception(
                    sprintf('Cannot find element by name "%s" in "%s"', $name, implode('.', $elementHierarchy))
                );
            }
            $element = $element->get($name);
        }
        return $element;
    }

    /**
     * Assert the type of a form element
     *
     * @param array  $elementHierarchy Form element name eg ['fields','numOfCows']
     * @param string $type             Class name of the type
     */
    protected function assertFormElementType(array $elementHierarchy, $type)
    {
        $this->assertInstanceOf($type, $this->getFormElement($elementHierarchy));
    }

    /**
     * Assert that a form element with a value is NOT valid
     *
     * @param array        $elementHierarchy   Form element name eg ['fields','numOfCows']
     * @param mixed        $value              The value to be tested in the form element
     * @param string|array $validationMessages A single or an array of expected validation messages keys
     * @param array        $context            Form data context required to test the validation
     */
    protected function assertFormElementNotValid(array $elementHierarchy, $value, $validationMessages, array $context = [])
    {
        self::$testedElements[implode($elementHierarchy, '.')] = true;

        if (!is_array($validationMessages)) {
            $validationMessages = [$validationMessages];
        }

        $this->assertElementExists($elementHierarchy);
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
     * @param array $context          Any form context required for this validation
     *
     * @return void
     */
    protected function assertFormElementText($elementHierarchy, $min = 0, $max = null, array $context = [])
    {
        if ($min > 0) {
            $this->assertFormElementValid($elementHierarchy, str_pad('', $min, 'x'), $context);
        }
        if ($min > 1) {
            $this->assertFormElementNotValid($elementHierarchy, str_pad('', $min - 1, 'x'),
                Validator\StringLength::TOO_SHORT, $context);
        } else {
            $this->assertFormElementValid($elementHierarchy, 'x', $context);
        }

        if ($max !== null) {
            $this->assertFormElementValid($elementHierarchy, str_pad('', $max, 'x'), $context);
            $this->assertFormElementNotValid(
                $elementHierarchy,
                str_pad('', $max + 1, 'x'),
                Validator\StringLength::TOO_LONG,
                $context
            );
        }
    }

    /**
     * Assert than a form element is a number input
     *
     * @param array        $elementHierarchy   Form element name eg ['fields','numOfCows']
     * @param int          $min                Minimum allowed value
     * @param int          $max                Maximum allowed value
     * @param string|array $validationMessages A single or an array of expected validation messages keys
     *
     * @return void
     */
    protected function assertFormElementNumber($elementHierarchy, $min = 0, $max = null, $validationMessages = null)
    {
        $this->assertFormElementValid($elementHierarchy, $min);
        $this->assertFormElementValid($elementHierarchy, $min + 1);

        if ($min > 0) {
            $this->assertFormElementNotValid(
                $elementHierarchy,
                $min - 1,
                $validationMessages ? : Validator\Between::NOT_BETWEEN
            );
        }

        if ($max !== null) {
            $this->assertFormElementValid($elementHierarchy, $max);
            $this->assertFormElementNotValid(
                $elementHierarchy,
                $max + 1,
                $validationMessages ? : Validator\Between::NOT_BETWEEN
            );
        }

        if ($validationMessages === null) {
            $this->assertFormElementNotValid($elementHierarchy, 'X', [Validator\Digits::NOT_DIGITS]);
        }
    }

    /**
     * Assert than a form element is a float input
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     * @param int   $min              Minimum allowed value
     * @param int   $max              Maximum allowed value
     *
     * @return void
     */
    protected function assertFormElementFloat($elementHierarchy, $min = 0, $max = null)
    {
        $this->assertFormElementValid($elementHierarchy, $min);
        $this->assertFormElementValid($elementHierarchy, $min + 0.1);

        if ($min > 0) {
            $this->assertFormElementNotValid($elementHierarchy, $min - 0.1, Validator\Between::NOT_BETWEEN);
        }

        if ($max !== null) {
            $this->assertFormElementValid($elementHierarchy, $max);
            $this->assertFormElementNotValid($elementHierarchy, $max + 0.1, Validator\LessThan::NOT_LESS_INCLUSIVE);
        }

        $this->assertFormElementNotValid($elementHierarchy, 'X', [\Zend\I18n\Validator\Float::NOT_FLOAT]);
    }

    /**
     * Assert than a form element is a checkbox input
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementCheckbox($elementHierarchy)
    {
        $this->assertFormElementValid($elementHierarchy, 'Y');
        $this->assertFormElementValid($elementHierarchy, 'N');
        $this->assertFormElementNotValid($elementHierarchy, 'X', [Validator\InArray::NOT_IN_ARRAY]);
    }

    /**
     * Assert than a form element is a hidden input
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementHidden($elementHierarchy)
    {
        $this->assertFormElementRequired($elementHierarchy, false);
        $this->assertFormElementAllowEmpty($elementHierarchy, true);
        $this->assertFormElementValid($elementHierarchy, 'X');
    }

    /**
     * Assert than a form element is a html input
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementHtml($elementHierarchy)
    {
        $this->assertFormElementRequired($elementHierarchy, false);
        $this->assertFormElementAllowEmpty($elementHierarchy, true);
        $this->assertFormElementValid($elementHierarchy, 'X');
    }

    /**
     * Assert than a form element is a action button input
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementActionButton($elementHierarchy)
    {
        $this->assertFormElementRequired($elementHierarchy, false);
        $this->assertFormElementAllowEmpty($elementHierarchy, true);
        $this->assertFormElementValid($elementHierarchy, 'X');
    }

    /**
     * Assert than a form element is a username input
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementUsername($elementHierarchy)
    {
        $this->assertFormElementText($elementHierarchy, 2, 40);

        $this->assertFormElementValid($elementHierarchy, '0123456789');
        $this->assertFormElementValid($elementHierarchy, 'abcdefghijklmnoprstuvwxyz');
        $this->assertFormElementValid($elementHierarchy, 'ABCDEFGHIJKLMNOPRSTUVWXYZ');
        $this->assertFormElementValid($elementHierarchy, '#$%\'+-/=?^_.@`|~",:;<>');

        $this->assertFormElementNotValid($elementHierarchy, 'a¬b', TransferValidator\Username::USERNAME_INVALID);
        $this->assertFormElementNotValid($elementHierarchy, 'a!b', TransferValidator\Username::USERNAME_INVALID);
        $this->assertFormElementNotValid($elementHierarchy, 'a£b', TransferValidator\Username::USERNAME_INVALID);
        $this->assertFormElementNotValid($elementHierarchy, 'a&b', TransferValidator\Username::USERNAME_INVALID);
        $this->assertFormElementNotValid($elementHierarchy, 'a*b', TransferValidator\Username::USERNAME_INVALID);
        $this->assertFormElementNotValid($elementHierarchy, 'a(b', TransferValidator\Username::USERNAME_INVALID);
        $this->assertFormElementNotValid($elementHierarchy, 'a)b', TransferValidator\Username::USERNAME_INVALID);
        $this->assertFormElementNotValid($elementHierarchy, 'a b', TransferValidator\Username::USERNAME_INVALID);
    }

    /**
     * Assert than a form element is an email address
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementEmailAddress($elementHierarchy)
    {
        $this->assertFormElementValid($elementHierarchy, 'valid@email.com');
        $this->assertFormElementValid(
            $elementHierarchy,
            '1234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890@'.
            '123456789012345678901234567890123456789012345678901234567890.com'
        );
        // total length greater than 254
        $this->assertFormElementNotValid(
            $elementHierarchy,
            '1234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890@'.
            '123456789012345678901234567890123456789012345678901234567890.'.
            '123456789012345678901234567890123456789012345678901234567890.'.
            '123456789012345678901234567890123456789012345678901234567890.com',
            TransferValidator\EmailAddress::ERROR_INVALID
        );
        // domain parts max greate than 63 chars
        $this->assertFormElementNotValid(
            $elementHierarchy,
            '1234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890'.
            '@1234567890123456789012345678901234567890123456789012345678901234.com',
            TransferValidator\EmailAddress::INVALID_FORMAT
        );
        $this->assertFormElementNotValid(
            $elementHierarchy,
            '1234567890123456789012345678901234567890123456789012345678901',
            TransferValidator\EmailAddress::INVALID_FORMAT
        );
    }

    /**
     * Assert than a form element is a postcode
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementPostcode($elementHierarchy)
    {
        $this->assertFormElementValid($elementHierarchy, 'LS9 6NF');
        $this->assertFormElementValid($elementHierarchy, 'ls9 6nf');
        $this->assertFormElementValid($elementHierarchy, 'ls96NF');
        $this->assertFormElementNotValid($elementHierarchy, 'not a postcode', Validator\StringLength::TOO_LONG);
    }

    /**
     * Assert than a form element is a phone
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementPhone($elementHierarchy)
    {
        $this->assertFormElementType($elementHierarchy, \Common\Form\Elements\InputFilters\Phone::class);
        $this->assertFormElementValid($elementHierarchy, '0123456789');
        $this->assertFormElementValid($elementHierarchy, '+44123456789');
        $this->assertFormElementValid($elementHierarchy, '(0044)1234567889');
        $this->assertFormElementValid($elementHierarchy, '0123-456789');
        $this->assertFormElementNotValid($elementHierarchy, 'not a phone number', Validator\Regex::NOT_MATCH);
    }

    /**
     * Note for developers
     * We are not really testing here.  There is a custom validation on the
     * frontend (mainly AJAX functionality).  For this purpose there is no real
     * use testing case.  So we skip these searchPostcode elements.
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementPostcodeSearch($elementHierarchy)
    {
        $searchPostcodeElements = [
            'postcode',
            'search',
            'addresses',
            'select',
            'manual-link',
        ];

        foreach ($searchPostcodeElements as $element) {
            $elementToSkip = array_merge(
                $elementHierarchy, [
                    $element,
                ]
            );

            self::$testedElements[implode($elementToSkip, '.')] = true;
        }
    }

    /**
     * Assert than a form element is a company number
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementCompanyNumber($elementHierarchy)
    {
        $this->assertFormElementText($elementHierarchy, 1, 8);
        $this->assertFormElementNotValid($elementHierarchy, '#', \Zend\I18n\Validator\Alnum::NOT_ALNUM);
    }

    /**
     * Assert than a form element is a company number type
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementCompanyNumberType($elementHierarchy)
    {
        $this->assertFormElementHtml(array_merge($elementHierarchy, ['description']));
        $this->assertFormElementCompanyNumber(array_merge($elementHierarchy, ['company_number']));
        $this->assertFormElementActionButton(array_merge($elementHierarchy, ['submit_lookup_company']));
    }

    /**
     * Assert than a form element is a table
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementTable($elementHierarchy)
    {
        $this->assertFormElementType($elementHierarchy, \Common\Form\Elements\Types\Table::class);
    }

    /**
     * Assert than a form element is a NoRender
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementNoRender($elementHierarchy)
    {
        $this->assertFormElementRequired($elementHierarchy, false);
        $this->assertFormElementAllowEmpty($elementHierarchy, true);
        $this->assertFormElementType($elementHierarchy, \Common\Form\Elements\InputFilters\NoRender::class);
    }

    /**
     * Assert than a form element is a VRM
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementVrm($elementHierarchy)
    {
        $this->assertFormElementValid($elementHierarchy, 'XX59 GTB');
        $this->assertFormElementValid($elementHierarchy, 'FOO1');
        $this->assertFormElementNotValid($elementHierarchy, 'FOO', 'invalid');
        $this->assertFormElementType($elementHierarchy, \Common\Form\Elements\Custom\VehicleVrm::class);
    }

    /**
     * Assert than a form element is a vehicle plated weight
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementVehiclePlatedWeight($elementHierarchy)
    {
        $this->assertFormElementNumber($elementHierarchy, 0, 999999);
        $this->assertFormElementType($elementHierarchy, \Common\Form\Elements\Custom\VehiclePlatedWeight::class);
    }

    /**
     * Assert that a form element is a dynamic multi checkbox
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     * @param bool  $required         Is the form element required
     *
     * @return void
     */
    protected function assertFormElementDynamicMultiCheckbox($elementHierarchy, $required = true)
    {
        $this->assertFormElementValid($elementHierarchy, 1);
        $this->assertFormElementValid($elementHierarchy, '1');
    }

    /**
     * Assert that a form element is a dynamic radio
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     * @param bool  $required         Is the form element required
     *
     * @return void
     */
    protected function assertFormElementDynamicRadio($elementHierarchy, $required = true)
    {
        $this->assertFormElementValid($elementHierarchy, 1);
        $this->assertFormElementValid($elementHierarchy, '1');
        if ($required) {
            $this->assertFormElementNotValid($elementHierarchy, 'X', Validator\InArray::NOT_IN_ARRAY);
        }
    }

    /**
     * Assert that a form element is a dynamic select
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
            // @todo uncomment the following line once "prefer_form_input_filter": true has been removed from the forms
            //$this->assertFormElementNotValid($elementHierarchy, 'X', Validator\InArray::NOT_IN_ARRAY);
        }
    }

    /**
     * Assert that a form element is a month select input
     *
     * @param array $elementHierarchy Form element name eg ['fields','numOfCows']
     *
     * @return void
     */
    protected function assertFormElementMonthSelect($elementHierarchy)
    {
        $this->assertFormElementValid($elementHierarchy, ['month' => '2', 'year' => '1999']);
        $this->assertFormElementNotValid(
            $elementHierarchy,
            ['month' => 'X', 'year' => '1999'],
            [
                \Zend\Validator\Regex::NOT_MATCH
            ]
        );
        $this->assertFormElementNotValid(
            $elementHierarchy,
            ['month' => '3', 'year' => 'XXXX'],
            [
                \Zend\Validator\Regex::NOT_MATCH
            ]
        );
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
     * Assert that a form element is a date time input.  For any complex
     * logic such as; `endDate` with contexts - use the individual methods.
     *
     * @param array     $elementHierarchy Form element name eg ['fields','numOfCows']
     * @param bool|true $required         Is this input required?  Default is 'true'
     * @param null      $value            Currently the default will be tomorrow's date
     *
     * @return void
     */
    protected function assertFormElementDateTime(array $elementHierarchy, $required = true, $value = null)
    {
        if ($value === null) {
            $currentDate = new \DateTimeImmutable('tomorrow');

            // Date inputted will be exact time tomorrow.
            $value = [
                'year' => $currentDate->format('Y'),
                'month' => $currentDate->format('m'),
                'day' => $currentDate->format('j'),
                'hour' => $currentDate->format('h'),
                'minute' => $currentDate->format('i'),
                'second' => $currentDate->format('s'),
            ];
        }

        $this->assertFormElementRequired($elementHierarchy, $required);
        $this->assertFormElementDateTimeNotValidCheck($elementHierarchy);
        $this->assertFormElementDateTimeValidCheck($elementHierarchy, $value);
    }

    /**
     * To avoid duplication, you can call this method separately and
     * pass custom validation messages
     *
     * @param array $elementHierarchy   Form element name eg ['fields','numOfCows']
     * @param array $validationMessages Specify if validation messages are expected to be different
     *
     * @return void
     */
    protected function assertFormElementDateTimeNotValidCheck(array $elementHierarchy, $validationMessages = [])
    {
        if (empty($validationMessages)) {
            $validationMessages = [
                \Common\Validator\Date::DATE_ERR_CONTAINS_STRING,
                \Common\Validator\Date::DATE_ERR_YEAR_LENGTH,
                Validator\Date::INVALID_DATE,
            ];
        }

        // String in values
        $this->assertFormElementNotValid(
            $elementHierarchy,
            [
                'year' => 'XXXX',
                'month' => 'XX',
                'day' => 'XX',
                'hour' => 'XX',
                'minute' => 'XX',
                'second' => 'XX',
            ],
            $validationMessages
        );

        $validationMessages = [
            Validator\Date::INVALID_DATE
        ];

        // Invalid date
        $this->assertFormElementNotValid(
            $elementHierarchy,
            [
                'year' => 2000,
                'month' => 15,
                'day' => 35,
                'hour' => 27,
                'minute' => 100,
                'second' => 5000,
            ],
            $validationMessages
        );
    }

    /**
     * Developer note;
     * Value is expected to be an array with 'year', 'month', 'day', 'hour', 'minute', 'second'
     *
     * @param array      $elementHierarchy Form element name eg ['fields','numOfCows']
     * @param null|mixed $value            Default date is tomorrows date.  Can be changed if future not allowed
     * @param array      $context          Context is normally used for startDate/endDates
     *
     * @return void
     */
    protected function assertFormElementDateTimeValidCheck(array $elementHierarchy, $value = null, array $context = [])
    {
        if ($value === null) {
            $currentDate = new \DateTimeImmutable('tomorrow');

            // Date inputted will be exact time tomorrow.
            $value = [
                'year' => $currentDate->format('Y'),
                'month' => $currentDate->format('m'),
                'day' => $currentDate->format('j'),
                'hour' => $currentDate->format('h'),
                'minute' => $currentDate->format('i'),
                'second' => $currentDate->format('s'),
            ];
        }

        // Valid scenario
        $this->assertFormElementValid($elementHierarchy, $value, $context);
    }

    /**
     * Assert whether a form element allows empty
     *
     * @param array        $elementHierarchy   Form element name eg ['fields','numOfCows']
     * @param bool         $allowEmpty         if true, form element allows empty
     * @param array        $context            Context
     * @param string|array $validationMessages A single or an array of expected validation messages keys
     *
     * @return void
     */
    protected function assertFormElementAllowEmpty(
        $elementHierarchy,
        $allowEmpty,
        $context = [],
        $validationMessages = null
    ) {
        if ($allowEmpty === true) {
            $this->assertFormElementValid($elementHierarchy, '', $context);
        } else {
            $this->assertFormElementNotValid(
                $elementHierarchy,
                '',
                $validationMessages ? : Validator\NotEmpty::IS_EMPTY,
                $context
            );
        }
    }

    /**
     * Assert whether a form element is required
     *
     * @param string       $elementHierarchy   Form element name
     * @param bool         $required           true, form element is required
     * @param string|array $validationMessages A single or an array of expected validation messages keys
     *
     * @return void
     */
    protected function assertFormElementRequired($elementHierarchy, $required, $validationMessages = null)
    {
        if ($required === true) {
            $this->assertFormElementNotValid(
                $elementHierarchy,
                null,
                $validationMessages ? : Validator\NotEmpty::IS_EMPTY
            );
        } else {
            $this->assertFormElementValid($elementHierarchy, null);
        }
    }

    /**
     * Check that tests exists for all form elements
     * This needs to be the last test that runs
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
