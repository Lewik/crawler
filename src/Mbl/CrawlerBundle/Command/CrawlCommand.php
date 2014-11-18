<?php


namespace Mbl\CrawlerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;

/** Class CrawlCommand */
class CrawlCommand extends Command
{
    /** @var Client */
    protected $client;

    /** @var  OutputInterface */
    protected $output;
    /** @var  int */
    protected $time;
    /** @var  array */
    protected $visitedLinks = [];
    /** @var  string */
    protected $currentLink;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('crawler:crawl')
            ->setDescription('Crawler crawl!');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->time = microtime(true);
        $output->writeln('start');

        $this->client = new Client();
        $this->output->writeln('client created');

        $menuTree = [];
        $menuTree['Товары'] = [];
        $this->output->writeln('Переходим на http://www.ru.all.biz/buy');
        $this->addLink('http://www.ru.all.biz/buy');
        $crawler = $this->client->request('GET', 'http://www.ru.all.biz/buy');
        //$crawler = $this->client->request('GET', 'http://www.ru.all.biz/buy-service');
        $menuTree['Товары'] = $this->volumeClick($crawler);
        var_dump($menuTree);

        $this->output->writeln('stop');
    }

    /**
     * @param $link
     */
    protected function addLink($link)
    {

        if (in_array($link, $this->visitedLinks)) {
            $this->output->writeln('<error>Ссылка уже была!:</error> ');
            $this->output->writeln('на ' . $this->currentLink);
            $this->output->writeln('переходим ' . $link);
        } else {
            $this->visitedLinks[] = $link;
        };
        $this->currentLink = $link;
    }


    /**
     * @param Crawler $crawler
     * @return array
     */
    protected function volumeClick(Crawler $crawler)
    {
        $tree = [];
        $this->output->writeln('Мы на странице раздела ' . $this->getSecs());
        $crawler->filter('.b-markets__left-menu a')->each(function ($node) use ($crawler, &$tree) {
            /** @var Crawler $node */
            $text = $node->text();
            $this->output->writeln('Переходим на страницу рынка: ' . $text);
            $link = $node->attr('href');
            $crawler = $this->click($link);
            $tree[$text] = $this->marketClick($crawler);

        });
        return $tree;
    }


    /**
     * @param Crawler $crawler
     * @return array
     */
    protected function marketClick(Crawler $crawler)
    {
        $tree = [];
        $this->output->writeln('Мы на странице рынка ' . $this->getSecs());
        $crawler->filter('.b-markets__top-categories_item_link')->each(function ($node) use ($crawler, &$tree) {
            /** @var Crawler $node */
            $link = $node->attr('href');
            $crawler = $this->click($link);
            $text = $node->filter('.b-markets__hoverlink')->html();
            $this->output->write('Переходим в категорию: ' . $text);
            $tree[$text] = $this->categoryClick($crawler);
            //var_dump($tree);
        });
        $this->output->writeln('Ок =) ' . $this->getSecs());
        die();
        return $tree;
    }

    /**
     * @param Crawler $crawler
     * @return array
     */
    protected function categoryClick(Crawler $crawler)
    {
        $tree = [];
        //$this->output->write('Мы на странице категории ' . $this->getSecs());
        $categoryTabActive = $crawler->filter('li.active')->text() == 'Категории';
        $filterTabExists = $crawler->filter('.nav-tabs li')->count() == 2;
        if (!$filterTabExists) {
            $this->output->write('<error>фильтра нет</error>');
        }

        if ($categoryTabActive && $filterTabExists) {
            $this->output->writeln(' - <info>есть подкатегории</info>');
            $crawler->filter('.b-rubricator-block a')->each(function ($node) use ($crawler, &$tree) {
                /** @var Crawler $node */
                $text = $node->text();
                $link = $node->attr('href');
                $this->output->write($this->getSecs() . ' Переходим в категорию: ' . $text . ' ' . $link);
                $crawler = $this->click($link);
                $subTree = $this->categoryClick($crawler);
                if ($subTree !== false) {
                    $tree[$text] = $subTree;
                } else {
                    $tree = $text;
                }
                //var_dump($tree);
            });
        } else {
            $this->output->writeln(' - конечная');
            $tree = false;
        }

        return $tree;
    }




    /**
     * @return string
     */
    protected function getSecs()
    {
        return '(' . (microtime(true) - $this->time) . ') сек';
    }


    /**
     * @param $uri
     * @return Crawler
     */
    protected function click($uri)
    {
        $this->addLink($uri);
        $crawler = $this->client->request('get', $uri);
        //file_put_contents(str_replace('/', '|', $uri . '.html'), $crawler->html());
        return $crawler;
    }
}