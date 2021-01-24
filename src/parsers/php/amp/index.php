<?php

define("START_TIME", microtime(true));

require_once "vendor/autoload.php";
$client = new Amp\Artax\DefaultClient;

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

function is_404($url) {
  $headers = get_headers($url);
  $resp_code = substr($headers[0], 9, 3);
  return $resp_code == "404" ? true : false;
}

function sanitize_url($url) {
  $url = urldecode($url);
  $url = filter_var($url, FILTER_SANITIZE_URL);
  if (filter_var($url, FILTER_VALIDATE_URL)) {
    return $url;
  } else {
    return false;
  }
}

// -- we have now passed the initial sanity checks --

function send_message($msg, $event = "message") {
  echo "event: $event\n";
  echo "data: $msg
  \n\n";
  ob_flush();
  flush();
}

function parse_page($html, $num) {
  $full_page = '';

  $page = new DOMDocument();
  $page->loadHTML($html, LIBXML_NOERROR | LIBXML_COMPACT);

  if ($page) {
    $xpath = new DOMXpath($page);
    // remove the pager
    foreach ($xpath->query('//p[@class="pages"]') as $pager) {
      $pager->parentNode->removeChild($pager);
    }

    // the goods are in one of these divs
    $recap = false;
    $recaps = $xpath->query('//div[@class="body_recap"]');
    foreach ($recaps as $div) {
      $recap = true;
      $div = check_links($div, $page);
      $full_page .= trim($page->savehtml($div));
    }
    if (!$recap) {
      $posts = $xpath->query('//div[@class="blog_post"]/p');
      foreach ($posts as $p) {
        $p = check_links($p, $page);
        $full_page .= trim($page->savehtml($p));
      }
    }

    // can't have new lines in the event message
    $full_page = str_replace(array("\n", "\r", "\t"), '', $full_page);

    // free memory
    unset($page);
    unset($xpath);
    return $full_page;
  } 
  else {
    return "<p>Failed fetching page: (url)</p>";
  }
}

function check_links($el) {
  foreach ($el->getElementsByTagName('a') as $link) {
    if (!$link->getAttribute('target')) {
      $link->setAttribute('target', '_blank');
    }
  }
  return $el;
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
$title_array = explode(" - ", $title);
$format = '<h4 class="subtitle is-4"><a href="%s" target="_blank">%s: %s</a></h4>';
$title_linked = sprintf($format, $pages_array[1], $title_array[0], $title_array[1]);

// $final_page will be the single big page
$final_page = $title_linked;

$promises = [];

foreach ($pages_array as $page_num => $pager_url) {
  if ($pager_url != $url) {
    $promises[$page_num] = Amp\call(function () use ($client, $pager_url) {
      // "yield" inside a coroutine awaits the resolution of the promise
      // returned from Client::request(). The generator is then continued.
      $response = yield $client->request($pager_url);

      // Same for the body here. Yielding an Amp\ByteStream\Message
      // buffers the entire message.
      $body = yield $response->getBody();

      return $body;
    });
  }
  else {
    $missing_page = $page_num;
  }
}

send_message('Assmebling the page...');

$responses = Amp\Promise\wait(Amp\Promise\all($promises));
$responses[$missing_page] = $dom->saveHTML();
ksort($responses);

foreach ($responses as $num => $html) {
  $pages[$num] = parse_page($html, $num);
}

foreach ($pages as $num => $page) {
  $final_page .= '<p class="page">-- Page ' . $num . ' --</p>';
  $final_page .= $page;
}

send_message('PRE-DONE', 'pre-done');

$final_page .= sprintf('<p class="exec-time">Generated in %.4f seconds.', microtime(true) - START_TIME);
send_message($final_page);

send_message('DONE', 'done');
