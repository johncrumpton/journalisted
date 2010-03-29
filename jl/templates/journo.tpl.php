<?php

/*
 Template for the main content of the journo page

 values available are:

 $id           - id of journo in database
 $prettyname   - eg "Bob Smith"
 $ref          - eg "bob-smith"
 $oneliner     - oneline description for journo (eg "The Guardian, The Observer")
 $status       - status of this journo:
                'a'=active, 'i'=inactive, 'h'=hidden
                 (should be 'a' can be 'i' or 'h' only if $can_edit_page is set)
 $rssurl       - url of RSS feed for this page

 $picture      - array with picture of journo (or null)
    width
    height
    url        - url of image


 $writtenfor   - eg "The Sun, The Mirror and The Daily Telegraph"

 $known_email  - email address details (or null)
    email      - email address
    srcurl     - source of address (eg a news article)
    srcurlname - description of source (eg "The Guardian")

 $guessed      - array of guessed contact details (or null)
    orgname    -
    orgphone   -
    emails     - array of email addresses

 $phone_number - phone number

 $twitter_id   - if journo has one, else null
 $twitter_url  - url of journos twitter feed (or null)

 $bios         - a list of bios for this journo
   for each one:
    bio        - a short bio
    srcurl     - eg "http://wikipedia.com/bob_smith"
    srcname    - eg "wikipedia"

 $employers    - list of employers journo has worked for
   for each one:
    employer   - name eg "Pig Farmer Monthly"
    job_title  - eg "Political Editor"
    year_from  - eg "2005"
    year_to
    current    - boolean flag indicating still employed in this job

 $education    - list of education entries
   for each one:
    school        - name of school
    field         - name of field studied (or '')
    qualification - qualification attained (or '')
    year_from     -
    year_to       -

 $books        - list of books written by this journo
   for each one:
    title          -
    year_published - 
    publisher      - name of publisher eg "Random House"

 $awards       - list of awards won by this journo
   for each one:
    award          - description of the award (eg "Nobel Prize for Chemistry")
    year            - (eg "2009", or NULL)

 $articles     - list of the most recent articles the journo has written
   for each one:
    id - database id of article (NULL if from a site we don't scrape)
    title - title of article
    permalink - link to original article
    srcorgname - eg "The Daily Mail"
    iso_pubdate - eg "2007-12-28T22:35:00+00:00"
    pretty_pubdate - eg "Fri 28 December 2007"
    buzz - eg "4 blog posts, 96 comments"
    total_bloglinks - num of blog posts known to reference this article
    total_comments - num of comments known about this article around the web

 $most_blogged - the most blogged article in the last 6 months
 $most_commented - the most commented-upon article in the last 6 months
   both of these have:
    id
    title
    permalink
    srcorgname
    iso_pubdate
    pretty_pubdate
    total_bloglinks
    total_comments

 $links
    url
    kind  - 'blog', 'webpage', 'twitter', '' (other)
    description - (only set if kind='')

 $similar_journos - list of journos who write similar articles

 $can_edit_page - TRUE if journo is logged in and can edit this page

 $recent_changes - list of recent changes to the journo's profile
   for each entry:
    description - eg "added a previous employer"

 $quick_n_nasty  - if true, the rest of the fields are not set
                   (used if the data is not cached and we need to throw up something quickly.
                    the affected fields are all a little slower to calculate, so we don't want
                    to be doing it in response to user request)
 ===== non quick_n_nasty fields =====
 $num_articles - number of articles journo has written (only for publications we cover)
 $first_pubdate - date of earliest article we have for this journo eg "May 2007"
 $wc_avg       - average wordcount of this journos articles
 $wc_min       - min wordcount of an article by this journo
 $wc_max       - max wordcount of an article by this journo

 $toptag_alltime - top tag of all time for this journo
 $toptag_month  - top tag over the last month for this journo
 $tags          - list of tags used by this journo
                  as an array of tag=>freq pairs
                  eg array( 'economy'=>65, 'sucks'=>1023 )
                  in alphabetical order

 $monthly_stats - array of stats for the last 12 months. Indexed by "YYYY-MM".
                num_articles
                avg_words
                search_url  - a /serach url to return that months articles

*/

// TODO: move this out of template...
$tab = get_http_var( 'tab', 'work' );
if( $tab != 'bio' && $tab != 'contact' )
    $tab = 'work';


$MAX_ARTICLES = 5;  /* how many articles to show on journo page by default */


/* build up a list of _current_ employers */
$current_employment = array();
foreach( $employers as $emp ) {
    if( $emp['current'] )
        $current_employment[] = $emp;
}

/* list of previous employers (just employer name, nothing else) */
$previous_employers = array();
foreach( $employers as $emp ) {
    if( !$emp['current'] )
        $previous_employers[] = $emp['employer'];
}
$previous_employers = array_unique( $previous_employers );

?>

<?php if( $can_edit_page && $status != 'a' ) { ?>
<div class="not-public">
  <p><strong>Please Note:</strong>
  Your page is not yet publicly accessible.
  It will be switched on once you have <a href="/missing?j=<?= $ref ?>">added</a> five articles.
  </p>
</div>
<?php } ?>


<div class="main journo-profile">
<div class="head"></div>
<div class="body">


<div class="overview">
  <div class="head"><h2><a href="<?= $rssurl; ?>"><img src="/images/rss.gif" alt="RSS feed" border="0" align="right"></a><?= $prettyname; ?></h2></div>
  <div class="body">

    <div class="photo">
<?php if( $picture ) { ?>
      <img src="<?= $picture['url']; ?>" alt="photo" width="<?= $picture['width'] ?>" height="<?= $picture['height']; ?>" />
<?php } else { ?>
      <img width="135" height="135" src="/img/rupe.png" alt="no photo" />
<?php } ?>
  <?php if( $can_edit_page ) { ?> <a class="edit" href="/profile_photo?ref=<?= $ref ?>">edit</a><?php } ?>
    </div>

    <div class="fudge">
<?php /* if( $bios ) { ?>
    <div class="section bios">
      <h4>Bio</h4>
<?php foreach($bios as $bio ) { ?>
      <p class="bio-para"><?= $bio['bio']; ?>
        <div class="disclaimer">
          (source: <a class="extlink" href="<?= $bio['srcurl'];?>"><?= $bio['srcname'];?></a>)
        </div>
      </p>
<?php } ?>
    </div>
<?php } */ ?>


<?php /* if( !$quick_n_nasty && $writtenfor ) { ?>
    <p>
      <?= $prettyname; ?> has written articles published in <?= $writtenfor; ?>.
    </p>
<?php } */ ?>


<?php if( $current_employment ) { ?>
    <div class="section current-employment">

      <h4>Current</h4>
      <ul>
<?php   foreach( $current_employment as $e ) { ?>
<?php       if( $e['kind'] == 'e' ) { /* employment */ ?>
        <li><?php if($e['job_title']){ ?><span class="jobtitle"><?= $e['job_title'] ?></span> at <?php } ?><span class="publication"><?= $e['employer'] ?></span></li>
<?php       } else { /* freelance */ ?>
        <li>Freelance <?= $e['employer'] ? ' (' . $e['employer'] . ')' : '' ?></span></li>
<?php       } ?>
<?php   } ?>
      </ul>
    </div>
<?php } ?>

<?php if( $previous_employers ) { ?>
    <div class="section previous-employers">
      <h4>Experience</h4>
      <ul>
<?php foreach( $previous_employers as $e ) { ?>
        <li><span class="publication"><?= $e ?></span></li>
<?php } ?>
      </ul>
    </div>
<?php } ?>

<?php if( $twitter_id ) { ?>
    <div class="section twitter">
    <h4>Twitter</h4>
    <ul><li>@<a href="<?= $twitter_url ?>"><?= h($twitter_id) ?></a></li></ul>
    </div>
<?php } ?>

    </div> <!-- end fudge -->
    <div style="clear: both;"></div>

  </div>
</div>  <!-- end overview -->


<?php /* TAB SECTIONS START HERE */ ?>


<div class="tabs">
<ul>
<li class="<?= $tab=='work' ? 'current':'' ?>"><a href="#tab-work">Work</a></li>
<li class="<?= $tab=='bio' ? 'current':'' ?>"><a href="#tab-bio">Biography</a></li>
<li class="<?= $tab=='contact' ? 'current':'' ?>"><a href="#tab-contact">Contact</a></li>
</ul>
</div>


<div class="tab-content" id="tab-work">

<div class="previous-articles">
  <div class="head">
<?php if( sizeof($articles)<$MAX_ARTICLES ) { ?>
    <h3>Articles</h3>
<?php } else { ?>
    <h3>Latest <?= $MAX_ARTICLES ?> articles</h3>
<?php } ?>
  </div>
  <div class="body">
    <div class="search">
    <form action="/search" method="get">
    <label for="findarticles">Search previous articles</label>
    <input id="findarticles" type="text" name="q" value="" />
    <input type="hidden" name="by" value="<?= $ref ?>" />
    <input type="hidden" name="type" value="article" />
    <input type="submit" value="Search" />
    </form>
    </div>

  <ul class="art-list">

<?php $n=0; foreach( $articles as $art ) { ?>
    <li class="hentry">
        <h4 class="entry-title"><a class="extlink" href="<?= $art['permalink']; ?>"><?= $art['title']; ?></a></h4>
        <span class="publication"><?= $art['srcorgname']; ?>,</span>
        <abbr class="published" title="<?= $art['iso_pubdate']; ?>"><?= $art['pretty_pubdate']; ?></abbr>
        <?php if( $art['buzz'] ) { ?> (<?= $art['buzz']; ?>)<?php } ?><br/>
        <?php if( $art['id'] ) { ?> <a href="<?= article_url($art['id']);?>">More about this article</a><br/> <?php } ?>
    </li>
<?php ++$n; if( $n>=$MAX_ARTICLES ) break; } ?>
<?php if( !$articles ) { ?>
  <p>None known</p>
<?php } ?>

  </ul>

<?php if($more_articles) { ?>
  (<a href="/<?= $ref ?>?allarticles=yes">Show all articles</a>)
<?php } ?>

<p>Article(s) missing? If you notice an article is missing,
<a href="/missing?j=<?= $ref ?>">click here</a></p>
</div>
</div>


<div class="monthly-stats">
  <div class="head"<h3><?= $prettyname ?>'s published articles - last 12 months<sup><a href="#monthly-stats-footnote">*</a></sup></h3></div>
  <div class="body">
<?php if( !$quick_n_nasty ) { ?>
    <div id="monthly-stats-placeholder" style="width:100%;height:300px"></div>
    <div id="monthly-stats-footnote">
      <sup>*</sup>Based only on article from <a href="/faq/what-news-outlets-does-journalisted-cover">these publications</a>.
    </div>

    <ul>
      <li>
        <?= $num_articles ?> articles <?php if( $num_articles>0) { ?> (since <?= $first_pubdate ?>) <?php } ?>
      </li>
      <li>Average article: <?php printf( "%.0f", $wc_avg/30); ?> column inches (<?php printf( "%.0f", $wc_avg); ?> words)</li>
      <li>Shortest article: <?php printf( "%.0f", $wc_min/30); ?> column inches (<?php printf( "%.0f", $wc_min); ?> words)</li>
      <li>Longest article: <?php printf( "%.0f", $wc_max/30); ?> column inches (<?php printf( "%.0f", $wc_max); ?> words)</li>
    </ul>
    <small>(<a href="/faq/what-are-column-inches">what are column inches?</a>)</small>
<?php
// some random colours...
$colours = array( 'purple', 'orange','yellowgreen','blue','yellow','green','red','skyblue' );
$parts = array();
$i=0;
foreach( $monthly_stats as $yearmonth=>$row ) {
    // convert to javascript timestamps
    $dt = new DateTime( "{$yearmonth}-01" );
    $jsts = (int)($dt->format('U')) * 1000;
    $avg_words = (int)$row['avg_words'];
    $r = 5 + ($avg_words*15)/1000;

    $parts[] = sprintf("{ x:%s, y:%s, r:%s, colour:'%s', avg_words: %s, search_url: '%s' }",
    	$jsts, $row['num_articles'], $r, $colours[ ($i++) % sizeof($colours)], $avg_words, $row['search_url'] );
}

/* output as a table */
/*
?>
<table id="monthlystats">
<thead>
 <tr>
  <th>month</th><th>num_articles</th><th>avg_words</th>
 </tr>
</thead>
<tbody>
<?php foreach( $monthly_stats as $yearmonth=>$row ) { ?>
 <tr>
   <td><?= $yearmonth ?></td><td><?= $row['num_articles'] ?></td><td><?= $row['avg_words'] ?></td>
 </tr>
<?php } ?>
</tbody>
</table>
<?php
*/
?>
<script language="javascript" type="text/javascript">
    $(document).ready( function() {
        var d = [ <?= implode( ",\n", $parts ) ?> ];
        jl.chart( "monthly-stats-placeholder", { data: d },
            {
                xaxis: { label: null, pad: [ 1000*60*60*24*7,1000*60*60*24*15 ], step: "month" },
                yaxis: { label: "Number of articles published", tickDecimals: 0, min: 0, pad: [0,0.5] }
            } );
    });
</script>


<?php } else { ?>
    <p>(sorry, information not currently available)</p>
<?php } ?>
  </div>
</div>


</div> <!-- end work tab -->




<div class="tab-content bio" id="tab-bio">


<div id="experience" class="experience">
  <div class="head">
    <h3>Experience</h3>
  </div>
  <div class="body">
<?php if( $employers ) { ?>
    <ul class="bio-list">
<?php foreach( $employers as $e ) { ?>

      <li>
  <?php if( $e['kind'] == 'e' ) { ?>
        <h4><?= $e['job_title'] ? $e['job_title'].', ' : '' ?><?= $e['employer'] ?></h4>
  <?php } else { ?>
        <h4>Freelance <?= $e['employer'] ? ' ('.$e['employer'].')' : ''?></h4>
  <?php } ?>

  <?php $year_from = $e['year_from'] ? $e['year_from'] : ''; ?>
  <?php $year_to = $e['current']?'present':$e['year_to']; ?>
  <?php if( $e['year_from'] || $e['year_to'] || $e['current'] ) { ?>
        <span class="daterange"><?= $year_from ?> - <?= $year_to ?></span>
  <?php } ?>
        <?php if( $can_edit_page ) { ?>
        <a class="edit"  href="/profile_employment?ref=<?= $ref ?>&action=edit&id=<?= $e['id']; ?>">[Edit]</a>
        <?php } ?>
      </li>



<?php } ?>
    </ul>
<?php } else { ?>
    <p class="not-known">No experience entered</p>
<?php } ?>
    <?php if( $can_edit_page ) { ?>
    <a class="add" href="/profile_employment?ref=<?= $ref ?>&action=new_employment">Add employment</a>&nbsp;&nbsp;
    <a class="add" href="/profile_employment?ref=<?= $ref ?>&action=new_freelance">Add freelance experience</a>
    <?php } ?>
  </div>
</div>


<div class="education">
  <div class="head">
    <h3>Education</h3>
  </div>
  <div class="body">
<?php if( $education ) { ?>
    <ul class="bio-list">
<?php foreach( $education as $edu ) { ?>
      <li>
        <h4><?= $edu['school']; ?></h4>
<?php if( $edu['qualification'] && $edu['field'] ) { ?>
        <?= $edu['qualification']; ?>, <?=$edu['field']; ?><br/>
<?php } ?>
        <?php if( $edu['year_from'] || $edu['year_to'] ) { ?>
        <span class="daterange"><?= $edu['year_from']; ?>-<?= $edu['year_to']; ?></span>
        <?php } ?>
        <?php if( $can_edit_page ) { ?>
        <a class="edit" href="/profile_education?ref=<?= $ref ?>&action=edit&id=<?= $edu['id'] ?>">[Edit]</a>
        <?php } ?>
      </li>
<?php } ?>
    </ul>
<?php } else { ?>
    <p class="not-known">No education entered</p>
<?php } ?>
    <?php if( $can_edit_page ) { ?>
    <a class="add"  href="/profile_education?ref=<?= $ref ?>&action=new_school">Add a school</a>&nbsp;&nbsp;
    <a class="add"  href="/profile_education?ref=<?= $ref ?>&action=new_uni">Add a university</a>
    <?php } ?>
  </div>
</div>


<div class="books">
  <div class="head">
    <h3>Books by <?= $prettyname ?></h3>
  </div>
  <div class="body">
<?php if( $books ) { ?>
    <ul class="bio-list">
<?php foreach( $books as $b ) { ?>
    <li>
      <h4><?= $b['title']; ?></h4>
      <?php if( $b['publisher'] || $b['year_published'] ) { ?>
      <?= $b['publisher']; ?>, <?= $b['year_published']; ?>
      <?php } ?>
      <?php if( $can_edit_page ) { ?>
      <a class="edit" href="/profile_books?ref=<?= $ref ?>&action=edit&id=<?= $b['id'] ?>">[Edit]</a>
      <?php } ?>
    </li>
<?php } ?>
    </ul>
<?php } else { ?>
    <p class="not-known">No books entered</p>
<?php } ?>
    <?php if( $can_edit_page ) { ?>
    <a class="add"  href="/profile_books?ref=<?= $ref ?>&action=new">Add book</a>
    <?php } ?>
  </div>
</div>


<div class="awards">
  <div class="head">
    <h3>Awards won</h3>
  </div>
  <div class="body">
<?php if( $awards ) { ?>
    <ul class="bio-list">
<?php foreach( $awards as $a ) { ?>
    <li>
      <h4><?= $a['award']; ?></h4>
      <?php if( $a['year'] ) { ?><?= $a['year'] ?><?php } ?>
      <?php if( $can_edit_page ) { ?>
      <a class="edit" href="/profile_awards?ref=<?= $ref ?>&action=edit&id=<?= $a['id'] ?>">[Edit]</a>
      <?php } ?>
    </li>
<?php } ?>
    </ul>
<?php } else { ?>
    <p class="not-known">No awards entered</p>
<?php } ?>
    <?php if( $can_edit_page ) { ?>
    <a class="add"  href="/profile_awards?ref=<?= $ref ?>&action=new">Add award</a>
    <?php } ?>
  </div>
</div>



</div> <!-- end bio tab -->



<div class="tab-content contact" id="tab-contact">


<div class="">
  <div class="head">
    <h3>Email</h3>
    <?php if( $can_edit_page ) { ?><a class="edit" href="/profile_contact?ref=<?= $ref ?>">edit</a><?php } ?>
  </div>
  <div class="body">
<?php if( $known_email ) { /* we've got a known email address - show it! */ ?>
    <p><span class="journo-email"><?= SafeMailTo( $known_email['email'] ); ?></span></p>
<?php if( $known_email['srcurl'] ) { ?>
    <span class="email-source">(from <a class="extlink" href="<?= $known_email['srcurl'] ?>"><?= $known_email['srcurlname'] ?></a>)</span>
<?php } ?>
<?php } ?>

<?php if( $guessed ) { /* show guessed contact details */ ?>
    <p class="not-known">No email address known for <?= $prettyname; ?>.</p>
    <p>You could <em>try</em> contacting <span class="publication"><?= $guessed['orgname']; ?></span>
    <?php if( $guessed['orgphone'] ) { ?> (Telephone: <?= $guessed['orgphone']; ?>) <?php } ?></p>
<?php
        if( $guessed['emails'] )
        {
            $safe_emails = array();
            foreach( $guessed['emails'] as $e )
                $safe_emails[] = SafeMailTo( $e );
?>
    <p>
    Based on the standard email format for <span class="publication"><?php echo $guessed['orgname']; ?></span>, the email address <em>might</em> be <?php echo implode( ' or ', $safe_emails ); ?>.
    </p>
<?php   } ?>
<?php } ?>

<?php if( !$guessed && !$known_email ) { ?>
    <p class="not-known">No email address known.</p>
<?php } ?>
  </div>
</div>


<div class="">
  <div class="head">
    <h3>Twitter</h3>
    <?php if( $can_edit_page ) { ?><a class="edit" href="/profile_contact?ref=<?= $ref ?>">edit</a><?php } ?>
  </div>
  <div class="body">
<?php if( $twitter_id ) { ?>
    <p>Find <?= $prettyname; ?> on twitter: @<a href="<?= $twitter_url ?>"?><?= h($twitter_id) ?></a></p>
<?php } else { ?>
    <p class="not-known">No Twitter account entered</p>
<?php } ?>
  </div>
</div>


<div class="">
  <div class="head">
    <h3>Phone</h3>
    <?php if( $can_edit_page ) { ?><a class="edit" href="/profile_contact?ref=<?= $ref ?>">edit</a><?php } ?>
  </div>
  <div class="body">
<?php if( $phone_number ) { ?>
    <p>Phone <?= $prettyname; ?> at: <?= h( $phone_number ) ?></p>
<?php } else { ?>
    <p class="not-known">No phone number entered</p>
<?php } ?>
  </div>
</div>


<div class="">
  <div class="head">
    <h3>Address</h3>
    <?php if( $can_edit_page ) { ?><a class="edit" href="/profile_contact?ref=<?= $ref ?>">edit</a><?php } ?>
  </div>
  <div class="body">
<?php if( $address ) { ?>
    <p>Write to <?= $prettyname ?> at:<br/><br/>
    <?= str_replace( "\n", "<br/>", h( $address ) ) ?>
<?php } else { ?>
    <p class="not-known">No address entered</p>
<?php } ?>
  </div>
</div>



</div> <!-- end contact tab -->
</div> <?php /* end main body */ ?>
<div class="foot"></div>
</div> <!-- end main -->




<div class="sidebar">

<a class="donate" href="http://www.justgiving.com/mediastandardstrust">Donate</a>

<div class="box subscribe-newsletter">
  <div class="head"><h3>journa<i>listed</i> weekly digest</h3></div>
  <div class="body">
    <p>To receive the journa<i>listed</i> digest every Tuesday via email, <a href="/weeklydigest">subscribe here</a></p>
  </div>
  <div class="foot"></div>
</div>

<div class="box you-can-also">
  <div class="head"><h3>You can also...</h3></div>
  <div class="body">
    <ul>
      <li class="add-alert"><a href="/alert?Add=1&amp;j=<?= $ref ?>">Add <?= $prettyname ?>'s articles to my daily alerts</a></li>
      <li class="print-page"><a href="#" onclick="javascript:window.print(); return false;" >Print this page</a></li>
      <li class="forward-profile"><a href="#">Forward profile to a friend</a></li>
<?php if( !$can_edit_page ) { ?>
      <li class="claim-profile">
        <a href="/profile?ref=<?= $ref ?>">Are you <?= $prettyname ?>?</a></li>
<?php } ?>
    </ul>
  </div>
  <div class="foot"></div>
</div>


<div class="box links">
  <div class="head"><h3><?= $prettyname ?> on the web</h3></div>
  <div class="body">
    <ul>
<?php foreach( $links as $l ) { ?>
       <li><a class="extlink" href="<?= $l['url'] ?>"><?= $l['description'] ?></a></li>
<?php } ?>
    </ul>
  </div>
  <div class="foot">
    <?php if( $can_edit_page ) { ?>
    <a class="edit" href="/profile_weblinks?ref=<?= $ref ?>">edit</a>
    <?php } ?>
  </div>
</div>




<div class="box">
  <div class="head"><h3>10 topics mentioned most by <?= $prettyname ?></h3></div>
  <div class="body">
    <div class="tags">
<?php
    if( !$quick_n_nasty ) {
        tag_display_cloud( $tags, $ref );
    } else {
?>
      <p>(sorry, information not currently available)</p>
<?php
    }
?>
    </div>
  </div>
  <div class="foot"></div>
</div>


<div class="box friendlystats">
  <div class="head"><h3><?= $prettyname ?> has written...</h3></div>
  <div class="body">
    <ul>
<?php if( !$quick_n_nasty && $toptag_alltime ) { ?>
      <li>More about '<a href ="<?= tag_gen_link( $toptag_alltime, $ref ) ?>"><?= $toptag_alltime ?></a>' than anything else</li>
<?php } ?>
<?php if( !$quick_n_nasty && $toptag_month ) { ?>
      <li>A lot about '<a href ="<?= tag_gen_link( $toptag_month, $ref ) ?>"><?= $toptag_month ?></a>' in the last month</li>
<?php } ?>
    </ul>
<?php /* journo_emitBasedDisclaimer(); */ ?>
  </div>
  <div class="foot"></div>
</div>


<?php if( !$quick_n_nasty && $most_blogged ) { ?>
<div class="box">
  <div class="head"><h3>Most blogged-about</h3></div>
  <div class="body">
    <ul>
     <li><a href="<?= article_url( $most_blogged['id'] );?>"><?= $most_blogged['title'];?></a>
     (<?= $most_blogged['total_bloglinks'] ?> blog posts)
     </li>
    <ul>
  </div>
  <div class="foot"></div>
</div>
<?php } ?>

<?php if( !$quick_n_nasty && $most_commented ) { ?>
<div class="box">
  <div class="head"><h3>Most commented-on</h3></div>
  <div class="body">
    <a href="<?= article_url( $most_commented['id'] );?>"><?= $most_commented['title'];?></a>
    (<?= $most_commented['total_comments'] ?> comments)
  </div>
  <div class="foot"></div>
</div>
<?php } ?>




<div class="box similar-journos">
  <div class="head"><h3>Journalists who write similar articles</h3></div>
  <div class="body">
    <ul>
<?php $n=0; foreach( $similar_journos as $j ) { ?>
      <li><?=journo_link($j) ?></li>
<?php if(++$n>=5) break; } ?>
    </ul>
  </div>
  <div class="foot">
    <small>(<a href="/faq/how-does-journalisted-work-out-what-journalists-write-similar-stuff">what's this?</a>)</small>
  </div>
</div>






<div class="box admired-journos">
 <div class="head"><h3>Journalists admired by <?= $prettyname ?></h3></div>
 <div class="body">
<?php if( $admired ) { ?>
  <ul>
<?php foreach( $admired as $a ) { ?>
   <li><?=journo_link($a) ?></li>
<?php } ?>
  </ul>
<?php } else { ?>
  <span class="not-known"><?= $prettyname ?> has not added any journalists</span>
<?php } ?>
 </div>
 <div class="foot">
<?php if( $can_edit_page ) { ?>
  <a class="edit" href="/profile_admired?ref=<?= $ref ?>">edit</a>
<?php } ?>
 </div>
</div>

</div>
</div> <!-- end sidebar -->

<?php /*
<script type="text/javascript" charset="utf-8">
      if(document.compatMode == 'CSS1Compat'){
        alert("Standards mode");
      }else{
        alert("Quirks mode");
      }
    </script>
*/ ?>

