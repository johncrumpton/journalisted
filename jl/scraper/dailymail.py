#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# TODO:
# - columnists require separate scrape path (no rss feeds!)?
#
# Notes:
# - www.dailymail.co.uk and www.mailonsunday.co.uk are interchangable
#

import re
from datetime import datetime
import sys
import urlparse

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup,NavigableString,Tag,Comment
from JL import ukmedia,ScraperUtils




# page which lists columnists and their latest rants
#columnistmainpage = 'http://www.dailymail.co.uk/pages/live/columnists/dailymail.html'


columnistmainpage = "http://www.dailymail.co.uk/debate/columnists/index.html"

columnistnames = None

def GetColumnistNames():
    """ Scrape a list of the columnist names from the columnist page (cached)"""
    global columnistnames
    if not columnistnames:
        columnistnames = []
        html = ukmedia.FetchURL( columnistmainpage )
        soup = BeautifulSoup( html )
        for a in soup.findAll( 'a', {'class':'author'} ):
            n = a.renderContents(None).strip()
            if not n in columnistnames:
                columnistnames.append( n )
    return columnistnames


def FindRSSFeeds():

#    blacklist = ( 'Pictures', 'Coffee Break', 'Live mag', 'You mag' )
    blacklist = ()
    feeds = []

    # page to read the list of rss feeds from
    rss_feed_page = "http://www.dailymail.co.uk/home/rssMenu.html"
    html = ukmedia.FetchURL( rss_feed_page )
    assert html.strip() != ''
    soup = BeautifulSoup( html )

    # look for rss icons, step back to find the links.
    for img in soup.findAll( 'img', {'src':re.compile('feeds_rss.gif$') } ):
        a = img.parent
        feed_url = urlparse.urljoin( rss_feed_page, a['href'] )
        # could get a more human-readable name, but relative url is good enough
        feed_name = a['href']
        feeds.append( (feed_name,feed_url) )

    assert len(feeds) > 120         # 168 feeds at time of writing

    return feeds




def Extract( html, context, **kw ):
    """ Extract dailymail article """

    art = context

    soup = BeautifulSoup( html )
    # quite possible that they still _really_ use windows-1252 despite
    # claiming iso-8859-1...
    # soup = BeautifulSoup( html, fromEncoding='windows-1252' )




    maindiv = soup.find( 'div', {'class': re.compile(r'\barticle-text\b') } )

    # pull out links to comments (at top of article)
    art['commentlinks'] = []
    commentlinks = maindiv.find( 'div', {'class': 'article-icon-links-container' } )
    if commentlinks:
        a = commentlinks.find('a',{'class':'comments-link'})
        if a:
            comment_url = urlparse.urljoin( art['srcurl'], a['href'] )

            cntspan = a.find('span', {'class': 'readerCommentNo'} )
            num_comments = 0
            if cntspan:
                cnttxt = cntspan.renderContents(None).strip()
                if cnttxt != u'-':
                    num_comments = int( cntspan.renderContents(None) )
            art['commentlinks'].append( {'num_comments':num_comments, 'comment_url':comment_url} )
        commentlinks.extract()


    # kill everything after article text
    cruftstart = maindiv.find( 'div', {'class': re.compile(r'\bintellicrumbs\b')} )
    if not cruftstart:
        cruftstart = maindiv.find( 'div', {'class': re.compile(r'\bprint-or-mail-links\b')} )
    if not cruftstart:
        cruftstart = maindiv.find( text=re.compile("google_ad_section_end[(]name=s2[)]") )
    if cruftstart:
        for cruft in cruftstart.findAllNext():
            cruft.extract()
        cruftstart.extract()

    desctxt = u''
    titletxt = u''
    bylinetxt = u''
    pubdatetxt = u''

    h1 = maindiv.find('h1')
    titletxt = ukmedia.FromHTMLOneLine( h1.renderContents(None) );

    # extract byline and date - first few paras after headline
    author_links = maindiv.findAll('a', {'class':re.compile(r'\bauthor\b')})
    timestamp_span = maindiv.find('span', {'class':re.compile(r'\barticle-timestamp\b')})


    if timestamp_span:
        # TODO: more than one timestamp (published and updated) but we'll take the first
        pubdatetxt = ukmedia.FromHTMLOneLine( timestamp_span.renderContents(None))
        timestamp_span.parent.extract()

    if pubdatetxt==u'':
        # fallback for old articles - check first few paras for date (can be in byline para)
        txt = u''
        for p in h1.findNextSiblings( 'p', limit=4 ):
            foo = ukmedia.FromHTMLOneLine( p.renderContents(None) );
            if re.search( r"^(By)|(Created)|(Last updated at)\s+",foo ):
                txt = txt + " " + foo

        pat = re.compile( r"(By\s+.*)?\s*(?:(?:Created\s+)|(?:Last updated at\s+))(.*$)" )
        m = pat.search( txt )
        if m is not None:
            pubdatetxt = m.group(2)

    if pubdatetxt==u'':
        # last ditch: use the meta tags:
        m_pub = soup.find( 'meta', {'property': "article:published_time" } )
        m_mod = soup.find( 'meta', {'property': "article:modified_time" } )
        if m_pub:
            pubdatetxt = m_pub.get('content',u'')
        if pubdatetxt==u'' and m_mod is not None:
            pubdatetxt = m_mod.get('content',u'')

    if author_links:
        authors = []
        for a in author_links:
            authors.append(ukmedia.FromHTMLOneLine( a.renderContents(None)));
        bylinetxt = ' and '.join(authors)

        author_links[0].parent.extract()



    if bylinetxt == u'':
        # columnists have no bylines, but might have a "More From ..." bit in <div class="columnist-archive"
        # (or "columnist-archive-narrow")
        columnistdiv = maindiv.find( 'div', {'class':re.compile('columnist-archive')} )
        if columnistdiv:
            h3 = columnistdiv.h3
            morefrompat = re.compile( ur'More from\s+(.*?)\s*[.]{3}', re.IGNORECASE )
            m = morefrompat.search( h3.renderContents(None) )
            bylinetxt = ukmedia.FromHTML( m.group(1) )

    if bylinetxt == u'':
        # last-ditch attempt - some columnists don't have bylines, but we might be able to guess them...
        m = soup.find( 'meta', {'name': "divclassbody" } )
        if m:
            id = m['content']   # eg "deborah-ross"
            for n in GetColumnistNames():
                if id == u'-'.join( n.lower().split() ):
                    bylinetxt = n
                    break

    art['title'] = u' '.join( titletxt.split() )
    art['byline'] = u' '.join( bylinetxt.split() )
    art['pubdate'] = ukmedia.ParseDateTime( pubdatetxt.strip() )


    if 0:
        # the date part...
        # eg "Last updated at 2:42 PM on 22nd May 2008"
        e = maindiv.find( text=re.compile( r'^\s*Last updated at' ) )
        if e:
            pubdatetxt = unicode(e)
            e.extract()
            art['pubdate'] = ukmedia.ParseDateTime( pubdatetxt.strip() )
        else:
            # no pubdate on page.... just make it up
            art['pubdate'] = datetime.now()



    # pull out images (<img> followed by <p class="imageCaption"> )
    art['images'] = []
    for captionp in maindiv.findAll( 'p', {'class':'imageCaption'} ):
        img = captionp.findPrevious( 'img' )
        if not img:
            continue

        img_caption = captionp.renderContents(None)
        img_credit = u''  # dailymail burns credit onto bottomleft of image
        img_url = img['src']
        art['images'].append( {'url': img_url, 'caption': img_caption, 'credit': img_credit } )


    # now extract article text

    # cruft removal
    for cruft in maindiv.findAll( 'h1' ):   # empty h1 is a BeautifulSoup artifact
        cruft.extract()
    for cruft in maindiv.findAll( 'img' ):
        cruft.extract()
    for cruft in maindiv.findAll( 'script' ):
        cruft.extract()
    for cruft in maindiv.findAll( 'p', {'class':'imageCaption'} ):
        cruft.extract()
    for cruft in maindiv.findAll( 'p', {'class':'scrollText'} ):
        cruft.extract()
    for cruft in maindiv.findAll( 'span', {'class':re.compile('^clickTo.*$') } ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':'clear'} ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':re.compile( r'\bmoduleFull\b' )} ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':re.compile( r'\bmoduleHalf\b' )} ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':re.compile('^related.*$') } ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':re.compile('^thinFloat') } ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':'columnist-archive' } ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':'floatRHS' } ):
        cruft.extract()
    for cruft in maindiv.findAll( 'a', {'class':re.compile('^lightbox') } ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':re.compile('ArtInlineReadLinks') } ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':'explore-links'} ):
        cruft.extract()
    contenttxt = maindiv.renderContents(None)

#    contenttxt = maindiv.prettify(None)
    contenttxt  = re.sub( r'</?(o|st1):.*?>', u'', contenttxt );

    contenttxt = ukmedia.SanitiseHTML( contenttxt )
    art['content'] = contenttxt

    if desctxt == u'':
        desctxt = ukmedia.FirstPara( contenttxt )
    desctxt = u' '.join( desctxt.split() )
    art['description'] = desctxt

    return art







def ScrubFunc( context, entry ):
    """mungefunc for ScraperUtils.FindArticlesFromRSS()"""

    # most dailymail RSS feeds go through feedburner, but luckily the original url is still there...
    url = context[ 'srcurl' ]
    url = TidyURL(url)
    if url.find('feedburner') != -1:
        url = entry.feedburner_origlink


    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
    return context


tidypat = re.compile( "^(.*?[.]html)(?:[?].*)?$" )

def TidyURL( url ):
    return tidypat.sub( r'\1', url )

# old style URLs:
# http://www.dailymail.co.uk/pages/live/articles/news/news.html?in_article_id=564447
# new style (from late may 2008):
# http://www.dailymail.co.uk/news/article-564447/Tories-ready-govern-moments-notice-insists-bullish-Cameron.html
#
# notes:
# - article id is same (hooray!)
# - old urls are redirected to new ones
# - text after article id ignored (redirected to canonical url)
#    Canonical url form appears to be:
#    http://www.dailymail.co.uk/news/article-564447/index.html
idpats = [
    re.compile( r"\bin_article_id=(\d+)" ),
    re.compile( r"/article-(\d+)/.*[.]html" )
    ]

def CalcSrcID( url ):
    """ Generate a unique srcid from a url """


    o = urlparse.urlparse( url )
    # blogs are handled by blogs.py
    if not o[1].endswith('dailymail.co.uk') and not o[1].endswith( 'mailonsunday.co.uk' ):
        return None

    for pat in idpats:
        m = pat.search( url )
        if m:
            return 'dailymail_' + m.group(1)
    return None


def ContextFromURL( url ):
    """Set up for scraping a single article from a bare url"""
    url = TidyURL(url)
    context = {
        'srcurl': url,
        'permalink': url,
        'srcid': CalcSrcID( url ),
        'srcorgname': u'dailymail', 
        'lastseen': datetime.now(),
    }
    return context


def FindArticles():
    """Look for recent articles"""

    rssfeeds = FindRSSFeeds()

    found = ScraperUtils.FindArticlesFromRSS( rssfeeds, u'dailymail', ScrubFunc )
    return found


if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=50 )

