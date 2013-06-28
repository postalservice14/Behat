<?php

namespace Behat\Behat\Formatter;

use Behat\Behat\Event\EventInterface,
    Behat\Behat\Event\FeatureEvent,
    Behat\Behat\Event\ScenarioEvent,
    Behat\Behat\Event\OutlineExampleEvent,
    Behat\Behat\Event\StepEvent,
    Behat\Behat\Exception\FormatterException;
use Behat\Behat\Event\SuiteEvent;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioNode;

/*
 * This file is part of the Behat.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Progress formatter.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class JUnitCombinedFormatter extends JUnitFormatter
{
    /**
     * @var array
     */
    protected $assertions = array();

    /**
     * Test scenarios.
     *
     * @var array
     */
    protected $testscenarios = array();

    /**
     * Test cases.
     *
     * @var array
     */
    protected $testcases = array();

    /**
     * Total exceptions count.
     *
     * @var integer
     */
    protected $exceptionsCount = array();

    /**
     * Step exceptions.
     *
     * @var array
     */
    protected $exceptions = array();

    /**
     * Start times.
     *
     * @var array
     */
    protected $startTimes;

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        $events = array(
            'beforeSuite', 'afterSuite', 'beforeFeature', 'afterFeature',
            'beforeScenario', 'afterScenario', 'beforeOutlineExample',
            'afterOutlineExample', 'afterStep'
        );

        return array_combine($events, $events);
    }

    /**
     * Listens to "suite.before" event.
     *
     * @param SuiteEvent $event
     *
     * @uses printTestSuiteHeader()
     */
    public function beforeSuite(SuiteEvent $event)
    {
        $this->printTestSuiteHeader();

        $this->testscenarios    = array();
        $this->testcases        = array();
        $this->exceptionsCount  = array();
        $this->startTimes['suite'] = microtime(true);
    }

    /**
     * Listens to "suite.after" event.
     *
     * @param SuiteEvent $event
     */
    public function afterSuite(SuiteEvent $event)
    {
        $this->printTestSuiteFooter(microtime(true) - $this->startTimes['suite']);
        $this->flushOutputConsole();
    }

    public function beforeFeature(FeatureEvent $event)
    {
        $this->startTimes['feature'] = microtime(true);
        $this->exceptionsCount['feature'] = 0;
        $this->assertions['feature'] = 0;
    }

    /**
     * Listens to "feature.after" event.
     *
     * @param FeatureEvent $event
     *
     * @uses printTestSuiteFooter()
     * @uses flushOutputConsole()
     */
    public function afterFeature(FeatureEvent $event)
    {
        $this->printFeatureHeader($event->getFeature(), microtime(true) - $this->startTimes['feature']);

        $this->assertions['suite'] += $this->assertions['feature'];
        $this->exceptionsCount['suite'] += $this->exceptionsCount['feature'];
    }

    /**
     * Listens to "scenario.before" event.
     *
     * @param ScenarioEvent $event
     */
    public function beforeScenario(ScenarioEvent $event)
    {
        $this->startTimes['scenario'] = microtime(true);
        $this->exceptionsCount['scenario'] = 0;
        $this->assertions['scenario'] = 0;
    }

    /**
     * Listens to "scenario.after" event.
     *
     * @param ScenarioEvent $event
     *
     * @uses printTestCase()
     */
    public function afterScenario(ScenarioEvent $event)
    {
        $this->printTestCase($event->getScenario(), microtime(true) - $this->startTimes['scenario'], $event);

        $this->assertions['feature'] += $this->assertions['scenario'];
        $this->exceptionsCount['feature'] += $this->exceptionsCount['scenario'];
    }

    /**
     * Listens to "outline.example.before" event.
     *
     * @param OutlineExampleEvent $event
     */
    public function beforeOutlineExample(OutlineExampleEvent $event)
    {
        $this->startTimes['scenario'] = microtime(true);
        $this->exceptionsCount['scenario'] = 0;
        $this->assertions['scenario'] = 0;
    }

    /**
     * Listens to "outline.example.after" event.
     *
     * @param OutlineExampleEvent $event
     *
     * @uses printTestCase()
     */
    public function afterOutlineExample(OutlineExampleEvent $event)
    {
        $this->printTestCase($event->getOutline(), microtime(true) - $this->startTimes['scenario'], $event);

        $this->assertions['feature'] += $this->assertions['scenario'];
        $this->exceptionsCount['feature'] += $this->exceptionsCount['scenario'];
    }

    /**
     * Listens to "step.after" event.
     *
     * @param StepEvent $event
     */
    public function afterStep(StepEvent $event)
    {
        if ($event->hasException()) {
            $this->exceptions[] = $event->getException();
            $this->exceptionsCount['scenario']++;
        }

        ++$this->assertions['feature'];
    }

    /**
     * Prints testsuite header.
     */
    protected function printTestSuiteHeader()
    {
        $this->writeln('<?xml version="1.0" encoding="UTF-8"?>');
        $this->writeln('<testsuites>');
    }

    /**
     * Prints testsuite footer.
     *
     * @param float       $time
     */
    protected function printTestSuiteFooter($time)
    {
        $suiteStats = sprintf('classname="behat.features" errors="0" failures="%d" name="" tests="%d" time="%F"',
            $this->exceptionsCount['suite'],
            $this->assertions['suite'],
            $time
        );

        $this->writeln("<testsuite $suiteStats>");
        $this->writeln(implode("\n", $this->testscenarios));
        $this->writeln('</testsuite>');
        $this->writeln('</testsuites>');
    }

    /**
     * Prints testcase.
     *
     * @param ScenarioNode   $scenario
     * @param float          $time
     * @param EventInterface $event
     */
    protected function printTestCase(ScenarioNode $scenario, $time, EventInterface $event)
    {
        $className  = $scenario->getFeature()->getTitle();
        $name       = $scenario->getTitle();
        $name      .= $event instanceof OutlineExampleEvent
                    ? ', Ex #' . ($event->getIteration() + 1)
                    : '';
        $caseStats  = sprintf('classname="%s" name="%s" time="%F"',
            htmlspecialchars($className),
            htmlspecialchars($name),
            $time
        );

        $xml  = "    <testcase $caseStats>\n";

        foreach ($this->exceptions as $exception) {
            $error = $this->exceptionToString($exception);
            $xml .= sprintf(
                '        <failure message="%s" type="%s">',
                htmlspecialchars($error),
                $this->getResultColorCode($event->getResult())
            );
            $exception = str_replace(array('<![CDATA[', ']]>'), '', (string) $exception);
            $xml .= "<![CDATA[\n$exception\n]]></failure>\n";
        }
        $this->exceptions = array();

        $xml .= "    </testcase>";

        $this->testcases[] = $xml;
    }

    /**
     * {@inheritdoc}
     */
    protected function createOutputStream()
    {
        $outputPath = $this->parameters->get('output_path');

        if (null === $outputPath) {
            throw new FormatterException(sprintf(
                'You should specify "output_path" parameter for %s', get_class($this)
            ));
        } elseif (is_dir($outputPath)) {
            throw new FormatterException(sprintf(
                'File path expected as "output_path" parameter of %s, but got directory: %s',
                get_class($this),
                $outputPath
            ));
        }

        return fopen($outputPath, 'w');
    }

    /**
     * @param FeatureNode $feature
     * @param float $time
     */
    private function printFeatureHeader(FeatureNode $feature, $time)
    {
        $featureFilePath = $this->parseFilename($feature->getFile());
        $featureNamespace = str_replace('.feature', '', $featureFilePath);
        $featurePackage = str_replace(DIRECTORY_SEPARATOR, '.', $featureNamespace);
        $suiteStats = sprintf('name="%s" file="%s" namespace="%s" fullpackage="%s" tests="%d" assertions="%d" failures="%d" errors="0" time="%F"',
            htmlspecialchars($feature->getTitle()),
            htmlspecialchars($featureFilePath),
            htmlspecialchars($featureNamespace),
            htmlspecialchars($featurePackage),
            count($this->testcases),
            $this->assertions['feature'],
            $this->exceptionsCount['feature'],
            $time
        );

        $this->testscenarios[] = "<testsuite $suiteStats>" . implode("\n", $this->testcases) . '</testsuite>';
    }

    /**
     * @param string $featureFilePath
     * @return string
     */
    private function parseFilename($featureFilePath)
    {
        $basePath = $this->getParameter('base_path');
        $featureFilePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $featureFilePath);
        return $featureFilePath;
    }
}
