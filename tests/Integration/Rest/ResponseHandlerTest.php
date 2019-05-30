<?php
declare(strict_types = 1);
/**
 * /tests/Integration/Rest/ResponseHandlerTest.php
 *
 * @author TLe, Tarmo Leppänen <tarmo.leppanen@protacon.com>
 */

namespace App\Tests\Integration\Rest;

use App\Rest\ResponseHandler;
use App\Rest\RestResourceInterface;
use App\Utils\Tests\ContainerTestCase;
use Exception;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class ResponseTest
 *
 * @package App\Tests\Integration\Rest
 * @author  TLe, Tarmo Leppänen <tarmo.leppanen@protacon.com>
 */
class ResponseHandlerTest extends ContainerTestCase
{
    public function testThatGetSerializerReturnsExpected(): void
    {

        $serializer = $this->getContainer()->get('serializer');

        $responseClass = new ResponseHandler($serializer);

        static::assertSame($serializer, $responseClass->getSerializer());
    }

    /**
     * @dataProvider dataProviderTestThatCreateResponseReturnsExpected
     *
     * @param Request $request
     * @param mixed   $data
     * @param string  $expectedContent
     */
    public function testThatCreateResponseReturnsExpected(
        Request $request,
        $data,
        string $expectedContent
    ): void {
        $serializer = $this->getContainer()->get('serializer');

        /** @var MockObject|RestResourceInterface $stubResourceService */
        $stubResourceService = $this->createMock(RestResourceInterface::class);

        $httpResponse = (new ResponseHandler($serializer))->createResponse($request, $data, $stubResourceService, 200);

        static::assertSame($expectedContent, $httpResponse->getContent());
    }

    /** @noinspection PhpFullyQualifiedNameUsageInspection */
    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\HttpException
     * @expectedExceptionMessage Some exception
     */
    public function testThatCreateResponseThrowsAnExceptionIfSerializationFails(): void
    {
        /**
         * @var MockObject|SerializerInterface $stubSerializer
         */
        $stubSerializer = $this->createMock(SerializerInterface::class);

        $request = Request::create('');

        $exception = new Exception('Some exception');

        $stubSerializer
            ->expects(static::once())
            ->method('serialize')
            ->withAnyParameters()
            ->willThrowException($exception);

        $responseClass = new ResponseHandler($stubSerializer);
        $responseClass->createResponse($request, []);
    }

    /** @noinspection PhpFullyQualifiedNameUsageInspection */
    /**
     * @dataProvider dataProviderTestThatNonSupportedSerializerFormatThrowsHttpException
     *
     * @expectedException \Symfony\Component\HttpKernel\Exception\HttpException
     * @expectedExceptionCode 400
     * @expectedExceptionMessageRegExp /Serialization for the format .* is not supported/
     *
     * @param string $format
     */
    public function testThatNonSupportedSerializerFormatThrowsHttpException(string $format): void
    {
        $request = Request::create('', 'GET', [], [], [], ['CONTENT_TYPE' => $format]);
        $serializer = $this->getContainer()->get('serializer');

        /** @var MockObject|RestResourceInterface $stubResourceService */
        $stubResourceService = $this->createMock(RestResourceInterface::class);

        (new ResponseHandler($serializer))
            ->createResponse($request, ['foo' => 'bar'], $stubResourceService, 200, $format);
    }

    public function testThatGetSerializeContextMethodCallsExpectedServiceMethods():void
    {
        /**
         * @var MockObject|SerializerInterface   $stubSerializer
         * @var MockObject|Request               $stubRequest
         * @var MockObject|ParameterBag          $stubParameterBag
         * @var MockObject|RestResourceInterface $stubResourceService
         */
        $stubSerializer = $this->createMock(SerializerInterface::class);
        $stubRequest = $this->createMock(Request::class);
        $stubParameterBag = $this->createMock(ParameterBag::class);
        $stubResourceService = $this->createMock(RestResourceInterface::class);

        $stubParameterBag
            ->expects(static::exactly(2))
            ->method('all')
            ->willReturn([]);

        $stubResourceService
            ->expects(static::once())
            ->method('getEntityName')
            ->willReturn('FakeEntity');

        $stubRequest->query = $stubParameterBag;

        $context = (new ResponseHandler($stubSerializer))->getSerializeContext($stubRequest, $stubResourceService);

        static::assertSame(['FakeEntity'], $context['groups']);
    }

    public function testThatGetSerializeContextSetExpectedGroupsWithPopulateAllParameterWhenNonAnyAssociations(): void
    {
        /**
         * @var MockObject|SerializerInterface   $stubSerializer
         * @var MockObject|Request               $stubRequest
         * @var MockObject|ParameterBag          $stubParameterBag
         * @var MockObject|RestResourceInterface $stubResourceService
         */
        $stubSerializer = $this->createMock(SerializerInterface::class);
        $stubRequest = $this->createMock(Request::class);
        $stubParameterBag = $this->createMock(ParameterBag::class);
        $stubResourceService = $this->createMock(RestResourceInterface::class);

        $stubParameterBag
            ->expects(static::exactly(2))
            ->method('all')
            ->willReturn(['populateAll' => '']);

        $stubResourceService
            ->expects(static::once())
            ->method('getEntityName')
            ->willReturn('FakeEntity');

        $stubRequest->query = $stubParameterBag;

        $context = (new ResponseHandler($stubSerializer))->getSerializeContext($stubRequest, $stubResourceService);

        static::assertSame(['FakeEntity'], $context['groups']);
    }

    public function testThatGetSerializeContextSetExpectedGroupsWithPopulateAllParameterWhenAssociations(): void
    {
        /**
         * @var MockObject|SerializerInterface   $stubSerializer
         * @var MockObject|Request               $stubRequest
         * @var MockObject|ParameterBag          $stubParameterBag
         * @var MockObject|RestResourceInterface $stubResourceService
         */
        $stubSerializer = $this->createMock(SerializerInterface::class);
        $stubRequest = $this->createMock(Request::class);
        $stubParameterBag = $this->createMock(ParameterBag::class);
        $stubResourceService = $this->createMock(RestResourceInterface::class);

        $stubParameterBag
            ->expects(static::exactly(2))
            ->method('all')
            ->willReturn(['populateAll' => '']);

        $stubResourceService
            ->expects(static::once())
            ->method('getEntityName')
            ->willReturn('FakeEntity');

        $stubResourceService
            ->expects(static::once())
            ->method('getAssociations')
            ->willReturn(['AnotherFakeEntity']);

        $stubRequest->query = $stubParameterBag;

        $context = (new ResponseHandler($stubSerializer))->getSerializeContext($stubRequest, $stubResourceService);

        static::assertSame(['FakeEntity', 'FakeEntity.AnotherFakeEntity'], $context['groups']);
    }

    public function testThatGetSerializeContextSetExpectedGroupsWithPopulateOnlyParameterWhenNonAssociations(): void
    {
        /**
         * @var MockObject|SerializerInterface   $stubSerializer
         * @var MockObject|Request               $stubRequest
         * @var MockObject|ParameterBag          $stubParameterBag
         * @var MockObject|RestResourceInterface $stubResourceService
         */
        $stubSerializer = $this->createMock(SerializerInterface::class);
        $stubRequest = $this->createMock(Request::class);
        $stubParameterBag = $this->createMock(ParameterBag::class);
        $stubResourceService = $this->createMock(RestResourceInterface::class);

        $stubParameterBag
            ->expects(static::exactly(2))
            ->method('all')
            ->willReturn(['populateOnly' => '']);

        $stubResourceService
            ->expects(static::once())
            ->method('getEntityName')
            ->willReturn('FakeEntity');

        $stubRequest->query = $stubParameterBag;

        $context = (new ResponseHandler($stubSerializer))->getSerializeContext($stubRequest, $stubResourceService);

        static::assertSame(['FakeEntity'], $context['groups']);
    }

    public function testThatGetSerializeContextSetExpectedGroupsWithPopulateOnlyParameterWhenEntityAssociations(): void
    {
        /**
         * @var MockObject|SerializerInterface   $stubSerializer
         * @var MockObject|Request               $stubRequest
         * @var MockObject|ParameterBag          $stubParameterBag
         * @var MockObject|RestResourceInterface $stubResourceService
         */
        $stubSerializer = $this->createMock(SerializerInterface::class);
        $stubRequest = $this->createMock(Request::class);
        $stubParameterBag = $this->createMock(ParameterBag::class);
        $stubResourceService = $this->createMock(RestResourceInterface::class);

        $stubParameterBag
            ->expects(static::exactly(2))
            ->method('all')
            ->willReturn(['populateOnly' => '']);

        $stubResourceService
            ->expects(static::once())
            ->method('getEntityName')
            ->willReturn('FakeEntity');

        $stubRequest
            ->expects(static::once())
            ->method('get')
            ->with('populate')
            ->willReturn(['AnotherFakeEntity']);

        $stubRequest->query = $stubParameterBag;

        $context = (new ResponseHandler($stubSerializer))->getSerializeContext($stubRequest, $stubResourceService);

        static::assertSame(['AnotherFakeEntity'], $context['groups']);
    }

    /** @noinspection PhpFullyQualifiedNameUsageInspection */
    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\HttpException
     * @expectedExceptionMessage Field 'foo': test error
     */
    public function testThatHandleFormErrorThrowsExpectedExceptionWithProperty(): void
    {
        /**
         * @var MockObject|SerializerInterface $serializer
         * @var MockObject|FormInterface       $formInterface
         * @var MockObject|FormError           $formError
         */
        $serializer = $this->createMock(SerializerInterface::class);
        $formInterface = $this->getMockBuilder(FormInterface::class)->getMock();
        $formError = $this->createMock(FormError::class);

        // Create FormErrorIterator
        $formErrorIterator = new FormErrorIterator($formInterface, [$formError]);

        $formInterface
            ->expects(static::once())
            ->method('getErrors')
            ->withAnyParameters()
            ->willReturn($formErrorIterator);

        $formInterface
            ->expects(static::once())
            ->method('getName')
            ->willReturn('foo');

        $formError
            ->expects(static::once())
            ->method('getOrigin')
            ->willReturn($formInterface);

        $formError
            ->expects(static::atLeast(1))
            ->method('getMessage')
            ->willReturn('test error');

        $testClass = new ResponseHandler($serializer);
        $testClass->handleFormError($formInterface);
    }

    /** @noinspection PhpFullyQualifiedNameUsageInspection */
    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\HttpException
     * @expectedExceptionMessage test error
     */
    public function testThatHandleFormErrorThrowsExpectedExceptionWithoutProperty(): void
    {
        /**
         * @var MockObject|SerializerInterface $serializer
         * @var MockObject|FormInterface       $formInterface
         * @var MockObject|FormError           $formError
         */
        $serializer = $this->createMock(SerializerInterface::class);
        $formInterface = $this->getMockBuilder(FormInterface::class)->getMock();
        $formError = $this->createMock(FormError::class);

        // Create FormErrorIterator
        $formErrorIterator = new FormErrorIterator($formInterface, [$formError]);

        $formInterface
            ->expects(static::once())
            ->method('getErrors')
            ->withAnyParameters()
            ->willReturn($formErrorIterator);

        $formInterface
            ->expects(static::once())
            ->method('getName')
            ->willReturn('');

        $formError
            ->expects(static::once())
            ->method('getOrigin')
            ->willReturn($formInterface);

        $formError
            ->expects(static::atLeast(1))
            ->method('getMessage')
            ->willReturn('test error');

        $testClass = new ResponseHandler($serializer);
        $testClass->handleFormError($formInterface);
    }

    /**
     * @return Generator
     */
    public function dataProviderTestThatCreateResponseReturnsExpected(): Generator
    {
        yield [
            Request::create(''),
            ['foo' => 'bar'],
            '{"foo":"bar"}'
        ];

        yield [
            Request::create('', 'GET', [], [], [], ['CONTENT_TYPE' => 'Some weird content type']),
            ['foo' => 'bar'],
            '{"foo":"bar"}'
        ];

        yield [
            Request::create('', 'GET', [], [], [], ['CONTENT_TYPE' => 'application/xml']),
            ['foo' => 'bar'],
            <<<DATA
<?xml version="1.0"?>
<response><foo>bar</foo></response>

DATA
        ];
    }

    /**
     * @return Generator
     */
    public function dataProviderTestThatNonSupportedSerializerFormatThrowsHttpException(): Generator
    {
        yield ['not supported format'];

        yield ['sjon'];

        yield ['lmx'];

        yield [''];
    }
}
