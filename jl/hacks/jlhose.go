package main

// implements an HTTP event stream server which streams out articles
// as they are added to the Journalisted database.

// TODO:
// 1) implement mechanism to access historical articles. Should probably
//    use different url, with parameters to specify a date range.
//    Don't make clients synthesise their own lastEventIDs! lastEventID is
//    just a meaningless token, from the stream consumer POV.
//
// 2) any lastEventID sent by client will currently be ignored. Should fix
//    but current eventsource.Repository interface doesn't feel like the
//    right abstraction for this case, where there's a database with a _large_
//    number of historical events... (eg worse case, you could have 10 million
//    articles to catch up on, and I can think of valid uses for that)
//    Anyway. needs a bit more thought and experimentation.
//
// 3) should think about updating articles too... where they are rescraped.
//    Should this be a separate event? No. Source doesn't necessarially know
//    that article is already in system (well, it could when slurping
//    out of the journalisted database say, but less stateful sources won't have
//    this info, so stream consumers have to cope with articles being resent).

// NOTES:
// - Consumer of stream should use canonical article URL (Permalink) as main
//   key to identify articles.
// - Articles _do_ have alternative URLs, which need to be stored and used for
//   lookups.
//

import (
	"database/sql"
	"encoding/json"
	"flag"
	"fmt"
	//_ "github.com/bmizerany/pq"
	"github.com/donovanhide/eventsource"
	_ "github.com/lib/pq"
	"net"
	"net/http"
	"strconv"
	"time"
)

// articleEvent encodes article data we want to stream out as events
type articleEvent struct {
	// internal article id
	id int
	// Permalink is the canonical URL
	Permalink string
	// TODO: VITAL TO INCLUDE ALTERNATE URLS FOR ARTICLE!
	// Title is the article headline
	Title       string
	Lastscraped time.Time
	Pubdate     time.Time
	Content     string
	// TODO: more fields!
	//  - journalisted url
	//  - publication info:  pretty name, home url etc..
}

func (art *articleEvent) Id() string {
	// TODO: currently just using internal article id as event id.
	// not too bad in practice - should always be ascending.
	return strconv.Itoa(art.id)
	// alternative:
	//	return strconv.Itoa(Lastscraped.Unix()) + "_" + strconv.Itoa(art.id)
}

func (art *articleEvent) Event() string {
	return "article"
}

func (art *articleEvent) Data() string {
	out, _ := json.Marshal(art)
	return string(out)
}

// findLatest returns the highest EventId currently in the article database
func findLatest(db *sql.DB) string {

	row := db.QueryRow("SELECT MAX(id) FROM article WHERE status='a'")
	var id int
	err := row.Scan(&id)
	if err != nil {
		panic(err)
	}

	return strconv.Itoa(id)
}

// pumpArticles streams out a batch of articles starting just after lastEventID
func pumpArticles(lastEventID string, db *sql.DB, eventServer *eventsource.Server) string {
	batchSize := 1000

	id, err := strconv.Atoi(lastEventID)
	if err != nil {
		panic(err)
	}

	// TODO:
	// - include alternate Urls from article_url table
	// - include publication name
	// - join with pub_set to restrict to uk national publications
	//   AND a.srcorg IN (SELECT pub_id FROM (pub_set_map m INNER JOIN pub_set s ON s.id=m.pub_set_id) WHERE name='national_uk')
	// - join with journo data?
	rows, err := db.Query(`
        SELECT a.id,a.permalink,a.title,a.pubdate,a.lastscraped,c.content
            FROM article a LEFT JOIN article_content c ON a.id=c.article_id
            WHERE a.id>$1
            ORDER BY id ASC
            LIMIT $2
        `, id, batchSize)

	if err != nil {
		panic(err)
	}

	for rows.Next() {
		var art articleEvent
		err = rows.Scan(&art.id, &art.Permalink, &art.Title, &art.Pubdate, &art.Lastscraped, &art.Content)
		if err != nil {
			panic(err)
		}
		eventServer.Publish([]string{"article"}, &art)
		lastEventID = art.Id()
		//		fmt.Printf("pub (%v)\n", art)
	}
	return lastEventID
}

func fetchArticles(db *sql.DB, timeBegin time.Time, timeEnd time.Time) ([]*articleEvent, error) {
	rows, err := db.Query(`
        SELECT a.id,a.permalink,a.title,a.pubdate,a.lastscraped,c.content
            FROM article a LEFT JOIN article_content c ON a.id=c.article_id
            WHERE lastscraped>=$1 AND lastscraped<$2
            ORDER BY lastscraped ASC
            LIMIT 10
        `, timeBegin, timeEnd)

	arts := make([]*articleEvent, 0, 256)
	for rows.Next() {
		var art articleEvent
		err = rows.Scan(&art.id, &art.Permalink, &art.Title, &art.Pubdate, &art.Lastscraped, &art.Content)
		if err != nil {
			return nil, err
		}
		arts = append(arts, &art)
	}
	return arts, nil
}

/*
type jlRepo []&articleEvent;

func (repo jlRepo) Get(channel, id string) Event {
}

func (repo jlRepo) Replay(channel, id string) (ids []string) {
}
*/

func main() {
	var port = flag.Int("port", 9999, "port to run server on")
	var interval = flag.Int("interval", 0, "how often db should be polled for new articles (0=never)")
	var tstart = flag.String("s", "", "time interval start yyyy-mm-dd")
	var tend = flag.String("e", "", "time interval end yyyy-mm-dd")
	var dbstring = flag.String("db", "user=jl dbname=jl host=/var/run/postgresql sslmode=disable", "connection string for database")
	flag.Parse()

	db, err := sql.Open("postgres", *dbstring)
	if err != nil {
		panic(err)
	}
	defer db.Close()

	if *tstart != "" || *tend != "" {
		timeStart, err := time.Parse("2006-01-02", *tstart)
		if err != nil {
			panic(err)
		}
		timeEnd, err := time.Parse("2006-01-02", *tend)
		if err != nil {
			panic(err)
		}
		fmt.Printf("%v -> %v\n", timeStart, timeEnd)
		arts, err := fetchArticles(db, timeStart, timeEnd)
		for i, art := range arts {
			fmt.Printf("%d: %s (%v)\n", i, art.Title, art.Lastscraped)
		}

		srv := eventsource.NewServer()
		repo := eventsource.NewSliceRepository()
		srv.Register("article", repo)
		for _, art := range arts {
			repo.Add("articles", art)
			srv.Publish([]string{"article"}, art)
		}

		defer srv.Close()
		http.HandleFunc("/article", srv.Handler("article"))
		l, err := net.Listen("tcp", fmt.Sprintf(":%d", *port))
		if err != nil {
			return
		}
		defer l.Close()
		fmt.Printf("running: %d articles in buffer\n", len(arts))
		http.Serve(l, nil)
		return
	}

	srv := eventsource.NewServer()
	defer srv.Close()
	http.HandleFunc("/new", srv.Handler("article"))
	l, err := net.Listen("tcp", fmt.Sprintf(":%d", *port))
	if err != nil {
		return
	}
	defer l.Close()
	go http.Serve(l, nil)

	// We poll the db at regular intervals to see if the highest article id has changed
	// (TODO: investigate postgresql pubsub stuff to avoid polling)
	lastEventID := findLatest(db)
	for {
		latest := findLatest(db)
		//fmt.Printf("latest=%s\n", latest)
		if latest != lastEventID {
			lastEventID = pumpArticles(lastEventID, db, srv)
		}
		time.Sleep(time.Duration(*interval) * time.Second)
	}
}
