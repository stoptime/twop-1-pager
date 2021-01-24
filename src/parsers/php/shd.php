<?php

define("START_TIME", microtime(true));

require "includes/simple_html_dom.php";

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

if (!empty($_GET['url'])) {
  $url = sanitize_url($_GET['url']);
  if ($url === false || is_404($url)) {
    sendMessage('<div class="notification is-danger">Not a valid URL or not found.</div>', 'not-found');
    die();
  }
}
else {
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
  } 
  else {
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
  $html_submitted = file_get_html($url);

  foreach ($html_submitted->find('p[class=pages] a') as $e) {
    $twop_pages[$e->plaintext] = $e->href;
  }

  unset($twop_pages['Next']);
  unset($twop_pages['Previous']);

  // we may not be on the 1st page
  // and if so, we'll need to sort
  $initial_count = count($twop_pages);
  // one page won't be linked
  $initial_count++;
  $sort = false;

  for ($i = 1; $i <= $initial_count; $i++) { 
    if (!array_key_exists($i, $twop_pages)) {
      $twop_pages[$i] = $url;
      $sort = true;
      break;
    }
  }

  if ($sort) {
    ksort($twop_pages);
  }

  $page_count = count($twop_pages);
  // key by page num, value wiil be html for that page
  $pages = [];

  sendMessage($page_count, "page-count");
  sendMessage("Found $page_count pages to parse.");

  $title = trim($html_submitted->find('title', 0)->plaintext);
  $title_array = explode(" - ", $title);
  $format = '<h4 class="subtitle is-4"><a href="%s" target="_blank">%s: %s</a></h4>';
  $title_linked = sprintf($format, $twop_pages[1], $title_array[0], $title_array[1]);

  $final_page = $title_linked;

  // loop through the pages
  // remembering we already have the submitted page object
  foreach ($twop_pages as $key => $val_url) {
    if ($val_url == $url) {
      $parsed_page = parsePage($val_url, $key, $html_submitted);
    }
    else {
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
    $page = file_get_html($url);
  }
  
  if ($page) {
    // remove the pager
    $page->find('p[class=pages]', 0)->outertext = '';

    // the goods are in one of these divs
    $recap = false;
    foreach ($page->find('div[class=body_recap]') as $div) {
      $recap = true;
      $div = check_links($div);
      $full_page .= $div->outertext;
    }
    if (!$recap) {
      foreach ($page->find('div[class=blog_post] p') as $p) {
        $p = check_links($p);
        $full_page .= $p->outertext;
      }
    }

    // free memory
    $page->clear(); 
    unset($page);
    return $full_page;
  }

  else {
    return "<p>Failed fetching page: $url</p>";
  }
}

function check_links($el) {
  foreach ($el->find('a') as $link) {
    if (!$link->target) {
      $link->target = "_blank";
    }
  }
  return $el;
}
