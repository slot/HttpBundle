<?php

namespace Slot\HttpBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Slot\HttpBundle\Command\Helper\DialogHelper;

class HttpClientCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('http:client')
            ->setDescription('HTTP Command Line Client')
            ->addArgument('url', InputArgument::REQUIRED, 'The URL zu query.')
            ->addOption('method', '', InputOption::VALUE_REQUIRED, '')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
        {

            $dialog = $this->getDialogHelper();

            $dialog->writeSection($output, 'HTTP client');
            $output->writeln(array('<comment>Query a URL with get or post method</comment>'));

            $method = $dialog->askAndValidate(
                $output,
                $dialog->getQuestion('Method to use (get, post)',
                $input->getOption('method')),
                function ($method) {
                    if (!in_array($method, array('get','post')))
                        {
                            throw new \RuntimeException('Method can be "get" or "post".');
                        }
                    return $method;
                },
                false,
                $input->getOption('method')
            );
            $input->setOption('method', $method);

            parent::interact($input, $output);

        }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getContainer()->get('slot_http.client');


        $url = $input->getArgument('url');
        $method = $input->getOption('method');

        $client->{$method}($url);

        $output->writeln(array('', '------------------------------', 'Response Headers:', '------------------------------'));

        foreach($client->getResponseHeader() as $name => $value)
        {
            $output->writeln(array($name . ': '  . $value));
        }

        $output->writeln(array('', '------------------------------', 'Response Body:', '------------------------------'));

        $output->writeln(array($client->getResponseBody()));

    }

    protected function getDialogHelper()
        {
            $dialog = $this->getHelperSet()->get('dialog');
            if (!$dialog || get_class($dialog) !== 'Sensio\Bundle\GeneratorBundle\Command\Helper\DialogHelper') {
                $this->getHelperSet()->set($dialog = new DialogHelper());
            }

            return $dialog;
        }
}