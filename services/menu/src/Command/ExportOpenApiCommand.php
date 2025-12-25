<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use OpenApi\Generator;

#[AsCommand(
    name: 'app:openapi:export',
    description: 'Export OpenAPI specification to a file',
)]
class ExportOpenApiCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path', 'openapi.json')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (json or yaml)', 'json')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $outputPath = $input->getOption('output');
        $format = $input->getOption('format');
        
        // Scan the src directory for OpenAPI attributes
        $openapi = Generator::scan([dirname(__DIR__)]);
        
        if ($format === 'yaml') {
            $content = $openapi->toYaml();
        } else {
            $content = $openapi->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        
        file_put_contents($outputPath, $content);
        
        $io->success("OpenAPI specification exported to {$outputPath}");
        
        return Command::SUCCESS;
    }
}
                    json_decode($spec->toJson(), true),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
            }

            file_put_contents($outputFile, $content);
            
            $io->success("OpenAPI specification exported to: {$outputFile}");
            
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error("Failed to generate OpenAPI spec: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
