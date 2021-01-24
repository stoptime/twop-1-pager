<?php

function is_404($url) {
  $headers = get_headers($url);
  $resp_code = substr($headers[0], 9, 3);
  return $resp_code == "404" ? true : false;
}

function sanitize_url($url) {
  $url = urldecode($url);
  $url = filter_var($url, FILTER_SANITIZE_URL);

  if ( substr($url, 0, 38) !== 'http://brilliantbutcancelled.com/show/' && 
    substr($url, 0, 42) !== 'http://www.brilliantbutcancelled.com/show/' ) {
    return false;
  }
  if (filter_var($url, FILTER_VALIDATE_URL)) {
    return $url;
  } 
  return false;
}

function send_message($msg, $event = "message") {
  echo "event: $event\n";
  echo "data: $msg
  \n\n";
  ob_flush();
  flush();
}

function check_links($el) {
  foreach ($el->getElementsByTagName('a') as $link) {
    if (!$link->getAttribute('target')) {
      $link->setAttribute('target', '_blank');
    }
  }
  return $el;
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
    // fix things like "rÃ©sumÃ©" (résumé)
    return utf8_decode($full_page);
  } 
  else {
    return "<p>Failed fetching page: (url)</p>";
  }
}

