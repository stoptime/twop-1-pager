# The TWOP 1 Pager

<img align="right" src="src/static/img/twop-24.png" width="250">

Pick a review, any review: http://www.brilliantbutcancelled.com/shows and plug it into https://twop1pager.com - and it will parse it down to 1 page and display a much nicer version. 

Behind the scenes this uses the php [Guzzle library](https://docs.guzzlephp.org/en/stable/) to asynchronously fetch pages all pages within a review, sort them, and return them to be displayed. While this is happening, this app leverages [server-sent events](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events/Using_server-sent_events) to keep the user up to date on how many pages are being fetched and the status of what is going on. 

This method works, but is still somewhat cumbersome - I will be moving this to Node.js to explore more interactivitely with events, and creating an api to store/display meta data about the review (author, date, grade, etc). Stay tuned! 📺
