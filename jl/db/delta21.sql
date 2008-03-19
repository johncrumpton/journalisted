

CREATE TABLE article_bloglink (
	id serial NOT NULL PRIMARY KEY,
	nearestpermalink text NOT NULL default '',
	title text NOT NULL default '',
	blogname text NOT NULL,
	blogurl text NOT NULL,
	linkurl text NOT NULL,
	linkcreated timestamp NOT NULL,
	excerpt text NOT NULL default '',
	via text NOT NULL default '',
	article_id integer REFERENCES article(id) NOT NULL
);

