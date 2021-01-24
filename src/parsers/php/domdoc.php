<?php

define("START_TIME", microtime(true));

// If you want to open this up for dev purposes...
// header('Access-Control-Allow-Origin: *');

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

if (!empty($_GET['url'])) {
  $url = sanitize_url($_GET['url']);
  if ($url === false || is_404($url)) {
    sendMessage('<div class="notification is-danger">Not a valid URL or not found.</div>', 'not-found');
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

getPages($url);

function sendMessage($msg, $event = "message") {
  echo "event: $event\n";
  echo "data: $msg
  \n\n";
  ob_flush();
  flush();
}

function getPages($url) {
  $dom = new DOMDocument();
  // suppress warning on crap markup
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

  // we may not be on the 1st page
  $initial_count = count($pages_array);
  // one page won't be linked
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

  $page_count = count($pages_array);
  // key by page num, value wiil be html for that page
  $pages = [];

  sendMessage($page_count, "page-count");
  sendMessage("Found $page_count pages to parse.");

  $title = trim($dom->getElementsByTagName("title")->item(0)->textContent);
  $title_array = explode(" - ", $title);
  $format = '<h4 class="subtitle is-4"><a href="%s" target="_blank">%s: %s</a></h4>';
  $title_linked = sprintf($format, $pages_array[1], $title_array[0], $title_array[1]);

  $final_page = $title_linked;

  // loop through the pages
  // remembering we already have the submitted page object
  foreach ($pages_array as $key => $val_url) {
    if ($val_url == $url) {
      $parsed_page = parsePage($val_url, $key, $dom);
    } else {
      $parsed_page = parsePage($val_url, $key);
    }
    $pages[$key] = $parsed_page;
  }

  sendMessage("Assembling pages...");

  foreach ($pages as $num => $page) {
    $final_page .= '<p class="page">-- Page ' . $num . ' --</p>';
    $final_page .= $page;
  }

  sendMessage('PRE-DONE', 'pre-done');

  $final_page .= sprintf('<p class="exec-time">Generated in %.4f seconds.', microtime(true) - START_TIME);

  sendMessage($final_page);

  sendMessage('DONE', 'done');
}

function parsePage($url, $num, $page = false) {
  sendMessage("Parsing page $num: " . $url);
  $full_page = '';

  if (!$page) {
    $page = new DOMDocument();
    $page->loadHTMLfile($url, LIBXML_NOERROR | LIBXML_COMPACT);
  }

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

    // simple_html_dom does this for us
    // can't have new lines in the event message
    $full_page = str_replace(array("\n", "\r", "\t"), '', $full_page);

    // free memory
    unset($page);
    unset($xpath);
    return $full_page;
  } 
  else {
    return "<p>Failed fetching page: $url</p>";
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
