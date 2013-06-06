<?php

namespace Issei\MthBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\DomCrawler\Crawler;

class CrawlerCommand extends ContainerAwareCommand
{
    private $cachePath, $logger;

    protected function configure()
    {
        $this
            ->setName('issei-mth:crawl')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->cachePath = $this->getContainer()->getParameter('issei_mth.current_url_cache_save_path');
        $this->logger    = $this->getContainer()->get('logger');

        $browser = $this->getContainer()->get('buzz.browser');
        $crawler = new Crawler();

        // キーワード -> URL のマッピング取得
        $urlMaps = json_decode(file_get_contents(__DIR__ . '/../Resources/config/urlmaps.json'), true);

        // 前回あたりだったページのURLマップを先頭に持ってくる (効率向上の為)
        $urlMapBeforeTime = $this->getUrlMapBeforeTime();
        if (null !== $urlMapBeforeTime) {
            unset($urlMaps[$urlMapBeforeTime['keyword']]);
            $urlMaps = array_merge(array($urlMapBeforeTime['keyword'] => $urlMapBeforeTime['url']), $urlMaps);
        }

        foreach ($urlMaps as $keyword => $url) {
            $response = $browser->get($url);

            // サーバーエラーは通知してスルー
            if (!$response->isSuccessful()) {
                $message = sprintf(
                    'Connection error in "%s", code: %d, message: "%s"',
                    $url, $response->getStatusCode(), $response->getReasonPhrase()
                );
                $output->writeLn('<error>' . $message . '</error>');
                $this->logger->err($message);

                continue;
            }

            $crawler->clear();
            $crawler->addContent($response->getContent());

            $isFailed = !!$crawler->filter('#failedImageChara')->count();

            // 成功した場合はキャッシュを作って退場
            if (false === $isFailed) {
                $message = sprintf('The keyword "%s" (url: "%s") is correct.', $keyword, $url);
                $output->writeln('<info>' . $message . '</info>');
                $this->logger->info($message);

                $this->createCacheForCorrectUrl($keyword, $url);

                return;
            }

            $message = sprintf('The keyword "%s" (url: "%s") is incorrect, pass it through.', $keyword, $url);
            $output->writeln($message);
            $this->logger->info($message);
        }

        // 1件も見つからない場合は通知
        $this->logger->err('No found correct page.');
    }

    private function getUrlMapBeforeTime()
    {
        $this->cachePath;

        if (is_file($this->cachePath)) {
            $data = include $this->cachePath;
            if (isset($data['url'])) {
                return $data;
            }
        }

        return null;
    }

    private function createCacheForCorrectUrl($keyword, $url)
    {
        $data = array(
            'keyword' => $keyword,
            'url' => $url
        );

        $line = '<?php return unserialize(\'' . serialize($data) . '\');';

        if (false === file_put_contents($this->cachePath, $line) || false === chmod($this->cachePath, 0644)) {
            $this->logger->err(sprintf('Failed to save the cache into "%s".', $cachePath));
        }
    }
}
