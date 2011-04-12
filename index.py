#!/usr/bin/env python

from BeautifulSoup import BeautifulSoup
from Cheetah.Template import Template
from urlparse import urlparse
import urllib2, cgi, re

# cheetah templates require the full path
this_dir = '/home/httpd/vhosts/givegoodweb.com/httpdocs/tools/twop/'
recapTitle = re.compile(r'headline_recap_title color[0-9]')

def index():
	to_return = Template( file = this_dir + "index.tmpl" )
	return to_return

# Receive the Request object
def show(req):
	uniqueLinks = []
	pageContent = []
	content = ''
	articleTitle = ''
	i = 1
	
	try:
		# The getfirst() method returns the value of the first field with the
		# name passed as the method argument
		url = req.form.getfirst('url', '')
		if url[0:4] != 'http':
			url = 'http://' + url
		if len(url) > 75: url_short = url[0:75] + "..."
		else: url_short = url
		parse = urlparse(url)
		root_domain = 'http://' + parse[1]

		# Escape the user input to avoid script injection attacks
		url = cgi.escape(url)
		
		#lets see if we can open the page, and catch the error if we can't
		try:
			page = urllib2.urlopen(url)
			soup = BeautifulSoup(page)
			
			try:
				title = soup.head.title.renderContents()
			except:
				title = '(no title)'
				
			pageList = soup.findAll('ul', {"class":"pages"})
			
			for link in pageList:
				allLinks = link.findAll('a')
			
			# Now, we need to make sure this is a unique, ordered list
			for l in allLinks:
				href = l.get('href')
				if href not in uniqueLinks: 
					uniqueLinks.append(href)
			
			# For each page in the review, we need to open it and get the review
			for page in uniqueLinks:
				soup2 = BeautifulSoup(urllib2.urlopen(page))
				# here we try to grab the review title from the 1st page
				# the html they use for this is not consistent, so need to try a few places
				if i == 1:
					try:
						header = soup2.find('span', {'class': recapTitle }) 
						articleTitle = header.renderContents()
					except:
						pass
					if len(articleTitle) == 0:
						try:
							header = soup2.findAll('div', {'class':'blog_header'}) 
							h = header[0].find('h1')
							articleTitle = h.renderContents()
						except:
							pass

				pageContent.extend('<p style="text-align: center">-- Page ' + str(i) + ' --</p>')
				i += 1
				recap = soup2.findAll('div', {"class":"body_recap"})
				if len(recap) == 0:
					recap = soup2.findAll('div', {"class":"blog_post"})
				for x in recap:
					allP = x.findAll('p')
					for p in allP: 
						pageContent.extend(str(p))
			
			content = ''.join(pageContent)
			namespace = { 'url': url, 'url_short': url_short, 'title': title, 'root_domain': root_domain, 'content': content, 'articleTitle': articleTitle}
			to_return = Template( file = this_dir + "show.tmpl", searchList = [namespace] )

			return to_return
			
		except urllib2.URLError, e:
			namespace = { 'error': 'Could not open the web page: ' + url }
			to_return = Template( file = this_dir + "error.tmpl", searchList = [namespace] )
			return to_return
		
	except (RuntimeError, AttributeError, TypeError, SyntaxError, UnboundLocalError, UnicodeDecodeError), e:
		namespace = { 'error': e }
		to_return = Template( file = this_dir + "error.tmpl", searchList = [namespace] )
		return to_return
			

