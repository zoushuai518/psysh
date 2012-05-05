<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use PHPParser_Lexer as Lexer;
use PHPParser_Parser as Parser;
use Psy\Command\Command;
use Psy\Output\ShellOutput;
use Psy\Util\Inspector;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Parse PHP code and show the abstract syntax tree.
 */
class ParseCommand extends Command
{
    private $parser;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('parse')
            ->setDefinition(array(
                new InputArgument('code', InputArgument::REQUIRED, 'PHP code to parse.'),
            ))
            ->setDescription('Parse PHP code and show the abstract syntax tree.')
            ->setHelp(<<<EOL
Parse PHP code and show the abstract syntax tree.
EOL
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $code  = $input->getArgument('code');

        if (strpos('<?', $code) === false) {
            $code = '<?php '.$code;
        }

        $walker = $this->getWalker();
        $nodes  = $this->parse($code);
        $output->page(function(ShellOutput $output) use (&$walker, $nodes) {
            $out = Inspector::export($nodes);
            $walker($output, $out);
        });
    }

    /**
     * Lex and parse a string of code into statements.
     *
     * @param string $code
     *
     * @return array Statements
     */
    private function parse($code)
    {
        return $this->getParser()->parse(new Lexer($code));
    }

    /**
     * Get (or create) the Parser instance.
     *
     * @return Parser
     */
    private function getParser()
    {
        if (!isset($this->parser)) {
            $this->parser = new Parser;
        }

        return $this->parser;
    }

    /**
     * Get a recursive formatting tree walker function.
     *
     * @return \Closure
     */
    private function getWalker()
    {
        $walker = function(ShellOutput $output, $tree, $depth = 0) use (&$walker) {
            $indent = str_repeat('  ', $depth);
            if (is_array($tree)) {
                if (empty($tree)) {
                    return $output->writeln('[]');
                }

                $keys = array_keys($tree);
                $isAssoc = !is_int(reset($keys));
                $output->writeln('[');
                foreach ($tree as $key => $node) {
                    if ($isAssoc) {
                        $output->write($indent.sprintf('  <comment>%s</comment>: ', $key));
                    } else {
                        $output->write($indent);
                    }
                    $walker($output, $node, $depth + 1);
                }
                $output->writeln($indent.']');
            } elseif (is_object($tree)) {
                $props = array_keys(json_decode(json_encode($tree), true));

                if (in_array('__CLASS__', $props)) {
                    $output->write(sprintf('  <<strong>%s</strong>> ', $tree->{'__CLASS__'}));
                    unset($props['__CLASS__']);
                }

                if (empty($props)) {
                    return $output->writeln('{}');
                }

                $output->writeln('{');

                foreach ($props as $prop) {
                    if ($prop === '__CLASS__') {
                        continue;
                    }
                    $output->write($indent.sprintf('  <info>%s</info>: ', $prop));
                    $walker($output, $tree->$prop, $depth + 1);
                }
                $output->writeln($indent.'}');
            } else {
                $output->writeln(sprintf('<return>%s</return>', json_encode($tree, true)));
            }
        };

        return $walker;
    }
}