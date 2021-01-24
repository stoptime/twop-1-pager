<?php

use GuzzleHttp\Client;
use GuzzleHttp\Promise;

define("START_TIME", microtime(true));

require_once "vendor/autoload.php";
require_once "includes/functions.php";

$client = new Client();

header("Access-Control-Allow-Origin: *");
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

if (!empty($_GET['url'])) {
  $url = sanitize_url($_GET['url']);
  if ($url === false || is_404($url)) {
    send_message('<div class="notification is-danger">Not a valid URL or not found.</div>', 'not-found');
    die();
  }
} else {
  die();
}

// -- fetch all the urls we'll need to parse from the pager --
$dom = new DOMDocument();
// suppress warning on crap markup | speed up
$dom->loadHTMLfile($url, LIBXML_NOERROR | LIBXML_COMPACT);
$xpath = new DOMXpath($dom);
$pages_array = [];
$pages = $xpath->query('//p[@class="pages"]');

foreach ($pages as $p) {
  $links = $p->getElementsByTagName("a");
  foreach ($links as $a) {
    $href = $a->getAttribute("href");
    $txt = trim($a->nodeValue);
    $pages_array[$txt] = $href;
  }
}

unset($pages_array['Next']);
unset($pages_array['Previous']);

// -- we may not be on the 1st page
// check and add in if needed
$initial_count = count($pages_array);
// the page we just got won't be linked
$initial_count++;
$sort = false;

for ($i = 1; $i <= $initial_count; $i++) {
  if (!array_key_exists($i, $pages_array)) {
    $pages_array[$i] = $url;
    $sort = true;
    break;
  }
}

if ($sort) {
  ksort($pages_array);
}
// -- end not 1st page check

$page_count = count($pages_array);
// key by page num, value will be html for that page
$pages = [];

send_message($page_count, 'total-pages');

$title = trim($dom->getElementsByTagName("title")->item(0)->textContent);
$title = str_replace("â¦", "...", $title);
$title_array = explode(" - ", $title);
$format = '<h4 class="subtitle is-4"><a href="%s" target="_blank">%s: %s</a></h4>';
$title_linked = sprintf($format, $pages_array[1], $title_array[0], $title_array[1]);

// $final_page will be the single big page
$final_page = $title_linked;

$promises = [];

foreach ($pages_array as $page_num => $pager_url) {
  if ($pager_url != $url) {
    $promises[$page_num] = $client->getAsync($pager_url);
  }
  else {
    $missing_page = $page_num;
  }
}

send_message('Assmebling the page...');

// Wait for the requests to complete; throws a ConnectException
// if any of the requests fail
$responses = Promise\unwrap($promises);

// Wait for the requests to complete, even if some of them fail
$responses = Promise\settle($promises)->wait();

$responses[$missing_page] = $dom->saveHTML();
ksort($responses);

foreach ($responses as $num => $html) {
  if (is_array($html)) {
    $pages[$num] = parse_page($html['value']->getBody()->getContents(), $num);
  }
  else {
    $pages[$num] = parse_page($html, $num);
  }
}

foreach ($pages as $num => $page) {
  $final_page .= '<p class="page">-- Page ' . $num . ' --</p>';
  $final_page .= $page;
}

send_message('PRE-DONE', 'pre-done');

$final_page .= sprintf('<p class="exec-time">Generated in %.4f seconds.</p>', microtime(true) - START_TIME);
send_message($final_page);

send_message('DONE', 'done');
