<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Tests\Integration;

use Piwik\API\DocumentationGenerator;
use Piwik\API\Proxy;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group Core
 */
class DocumentationGeneratorTest extends IntegrationTestCase
{
    public function test_CheckIfModule_ContainsHideAnnotation()
    {
        $annotation = '@hideExceptForSuperUser test test';
        $mock = $this->getMockBuilder('ReflectionClass')
            ->disableOriginalConstructor()
            ->setMethods(array('getDocComment'))
            ->getMock();
        $mock->expects($this->once())->method('getDocComment')->willReturn($annotation);
        $documentationGenerator = new DocumentationGenerator();
        $this->assertTrue($documentationGenerator->checkIfClassCommentContainsHideAnnotation($mock));
    }

    public function test_CheckDocumentation()
    {
        $moduleToCheck = 'this is documentation which contains @hideExceptForSuperUser';
        $documentationAfterCheck = 'this is documentation which contains ';
        $documentationGenerator = new DocumentationGenerator();
        $this->assertEquals($documentationGenerator->checkDocumentation($moduleToCheck), $documentationAfterCheck);
    }

    public function test_CheckIfMethodComment_ContainsHideAnnotation_andText()
    {
        $eventDispatcher = $this->getContainer()->get('Piwik\EventDispatcher');

        $annotation = '@hideForAll test test';
        $eventDispatcher->addObserver('API.DocumentationGenerator.@hideForAll',
            function (&$hide) {
                $hide = true;
            });
        $this->assertEquals(Proxy::getInstance()->shouldHideAPIMethod($annotation), true);
    }

    public function test_CheckIfMethodComment_ContainsHideAnnotation_only()
    {
        $eventDispatcher = $this->getContainer()->get('Piwik\EventDispatcher');

        $annotation = '@hideForAll';
        $eventDispatcher->addObserver('API.DocumentationGenerator.@hideForAll',
            function (&$hide) {
                $hide = true;
            });
        $this->assertEquals(Proxy::getInstance()->shouldHideAPIMethod($annotation), true);
    }

    public function test_CheckIfMethodComment_DoesNotContainHideAnnotation()
    {
        $eventDispatcher = $this->getContainer()->get('Piwik\EventDispatcher');

        $annotation = '@not found here';
        $eventDispatcher->addObserver('API.DocumentationGenerator.@hello',
            function (&$hide) {
                $hide = true;
            });
        $this->assertEquals(Proxy::getInstance()->shouldHideAPIMethod($annotation), false);
    }
}
