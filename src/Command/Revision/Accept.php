<?php

namespace CloudCompli\Adjutant\Command\Revision;

use CloudCompli\Adjutant\Command\Base;
use PHPGit\Git;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Accept extends Base
{
    public function __construct()
    {
        $this->basePath = realpath('.');
        $this->composerFilePath = $this->basePath.'/composer.json';

        $this->gitRepositoryPath = $this->basePath;
        $this->gitRepository = new Git();
        $this->gitRepository->setRepository($this->gitRepositoryPath);

        $this->gitRemoteName = 'upstream';

        return parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('revision:accept')
            ->setDescription('Generate a revision release and push it to the upstream remote')
            ->addOption(
                'auto',
                null,
                InputOption::VALUE_NONE,
                'If set, the task will automatically pass all questions'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $newVersionNumber = $this->incrementComposerVersion($input, $output);
        $this->commitMasterForVersion($input, $output, $newVersionNumber);
        $this->pushMasterForVersion($input, $output, $newVersionNumber);
        $this->createTagForVersion($input, $output, $newVersionNumber);
        $this->pushTagForVersion($input, $output, $newVersionNumber);
    }

    protected function incrementComposerVersion(InputInterface $input, OutputInterface $output)
    {
        $composerFile = $this->composerFilePath;
        $composerData = json_decode(file_get_contents($composerFile), true);
        $versionSegments = explode('.', $composerData['version']);

        if(count($versionSegments) < 2){
            $versionSegments[1] = '0';
        }

        if(count($versionSegments) < 3){

            $versionSegments[2] = '0';

        }else {

            $patchVersion = strpos($versionSegments[2], '-')
                ? explode('-', $versionSegments[2], 2)
                : $patchVersion = [$versionSegments[2]];

            $patchVersion[0] = intval($patchVersion[0]) + 1;

            $versionSegments[2] = implode('-', $patchVersion);
        }

        if(count($versionSegments) > 3){

            $versionSegments = array_slice($versionSegments, 0, 3);

        }

        $newVersionString = implode('.', $versionSegments);

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Use version '.$newVersionString.'?', $input->getOption('auto'));

        if (!$helper->ask($input, $output, $question)) {
            return;
        }

        $output->writeln('Updating version to '.$newVersionString);

        $composerData['version'] = $newVersionString;
        $composerData = json_encode($composerData, JSON_PRETTY_PRINT);

        file_put_contents($composerFile, $composerData);

        $output->writeln('Updated composer.json to version '.$newVersionString);

        return $newVersionString;
    }
    
    protected function commitMasterForVersion(InputInterface $input, OutputInterface $output, $newVersionString)
    {
        $this->gitRepository->add('composer.json');
        $this->gitRepository->commit('Version bump by Adjutant [ci skip]');
        $output->writeln('Commit to master for version '.$newVersionString);
    }
    
    protected function pushMasterForVersion(InputInterface $input, OutputInterface $output, $newVersionString)
    {
        $this->gitRepository->push($this->gitRemoteName, 'master');
        $output->writeln('Pushed master for version '.$newVersionString);
    }

    protected function createTagForVersion(InputInterface $input, OutputInterface $output, $newVersionString)
    {
        $this->gitRepository->tag->create($newVersionString);
        $output->writeln('Created tag '.$newVersionString);
    }

    protected function pushTagForVersion(InputInterface $input, OutputInterface $output, $newVersionString)
    {
        $this->gitRepository->push($this->gitRemoteName, $newVersionString);
        $output->writeln('Pushed tag '.$newVersionString.' to '.$this->gitRemoteName);
    }
}