<?php
/*
 * This file is part of the sfPropelFinder package.
 * 
 * (c) 2007 François Zaninotto <francois.zaninotto@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
You need a model built with a running database to run these tests.
The tests expect a model similar to this one:

    propel:
      article:
        id:          ~
        title:       varchar(255)
        category_id: ~
      article_i18n:
        content:     varchar(255)
      category:
        id:          ~
        name:        varchar(255)
      comment:
        id:          ~
        content:     varchar(255)
        article_id:  ~
        author_id:   ~
      author:
        id:          ~
        name:        varchar(255)

And a second model similar to:

    propel:
      _attributes: { package: lib.model.relations }
      sex:
        _attributes: { phpName: Civility }
        is_man:      boolean
      club:
        code:        varchar(100)
      club_i18n:
        motto:       varchar(255)
      person:
        id:          ~
        name:        varchar(255)
        foo:         { type: integer, foreignTable: club, foreignReference: id, onDelete: cascade }
        the_sex:     { type: integer, foreignTable: sex, foreignReference: id, onDelete: cascade }

Beware that the tables for these models will be emptied by the tests, so use a test database connection.
*/

include dirname(__FILE__).'/../../bootstrap.php';

$t = new lime_test(48, new lime_output_color());

$t->diag('with()');

CommentPeer::doDeleteAll();
ArticlePeer::doDeleteAll();
CategoryPeer::doDeleteAll();
$category1 = new Category();
$category1->setName('cat1');
$category1->save();
$article1 = new Article();
$article1->setTitle('aaaaa');
$article1->setCategory($category1);
$article1->save();
$sql = 'SELECT article.ID, article.TITLE, article.CATEGORY_ID, category.ID, category.NAME FROM article INNER JOIN category ON (article.CATEGORY_ID=category.ID) LIMIT 1';
$finder = sfPropelFinder::from('Article')->join('Category')->with('Category');
$article = $finder->findOne();
$t->is($finder->getLatestQuery(), $sql, 'with() gets the columns of the with class in addition to the columns of the current class');
$article->getCategory();
$t->is(Propel::getConnection()->getLastExecutedQuery(), $sql, 'with() hydrates the related classes and avoids subsequent queries');

$finder = sfPropelFinder::from('Article')->with('Category');
$article = $finder->findOne();
$t->is($finder->getLatestQuery(), $sql, 'with() adds a join if not already added');
$t->is($article->getTitle(), 'aaaaa', 'fetching objects with a with() returns the correct main object');
$t->is($article->getCategory()->getName(), 'cat1', 'fetching objects with a with() returns the correct related object');
$t->is(Propel::getConnection()->getLastExecutedQuery(), $sql, 'with() called without a join() hydrates the related classes and avoids subsequent queries');

$finder = sfPropelFinder::from('Article')->leftJoin('Category')->with('Category');
$article = $finder->findOne();
$t->is($finder->getLatestQuery(), 'SELECT article.ID, article.TITLE, article.CATEGORY_ID, category.ID, category.NAME FROM article LEFT JOIN category ON (article.CATEGORY_ID=category.ID) LIMIT 1', 'calling a particular join() before with() changes the join clause');

CommentPeer::doDeleteAll();
ArticlePeer::doDeleteAll();
AuthorPeer::doDeleteAll();
$article1 = new Article();
$article1->setTitle('bbbbb');
$article1->setCategory($category1);
$article1->save();
$author1 = new Author();
$author1->setName('John');
$author1->save();
$comment = new Comment();
$comment->setContent('foo');
$comment->setArticleId($article1->getId());
$comment->setAuthor($author1);
$comment->save();
$finder = sfPropelFinder::from('Comment')->with('Article')->with('Author');
$comment = $finder->findOne();
$sql = 'SELECT comment.ID, comment.CONTENT, comment.ARTICLE_ID, comment.AUTHOR_ID, article.ID, article.TITLE, article.CATEGORY_ID, author.ID, author.NAME FROM comment INNER JOIN article ON (comment.ARTICLE_ID=article.ID) INNER JOIN author ON (comment.AUTHOR_ID=author.ID) LIMIT 1';
$t->is($finder->getLatestQuery(), $sql, 'you can call with() several times to hydrate more than one related object');
$t->is($comment->getContent(), 'foo', 'you can call with() several times to hydrate more than one related object');
$t->is($comment->getArticle()->getTitle(), 'bbbbb', 'you can call with() several times to hydrate more than one related object');
$t->is($comment->getAuthor()->getName(), 'John', 'you can call with() several times to hydrate more than one related object');
$t->is(Propel::getConnection()->getLastExecutedQuery(), $sql, 'with() called several tims hydrates the related classes and avoids subsequent queries');

$sql = 'SELECT comment.ID, comment.CONTENT, comment.ARTICLE_ID, comment.AUTHOR_ID, article.ID, article.TITLE, article.CATEGORY_ID, category.ID, category.NAME FROM comment INNER JOIN article ON (comment.ARTICLE_ID=article.ID) INNER JOIN category ON (article.CATEGORY_ID=category.ID) LIMIT 1';
$finder = sfPropelFinder::from('Comment')->with('Article')->with('Category');
$comment = $finder->findOne();
$t->is($finder->getLatestQuery(), $sql, 'with() can even hydrate related objects via a related object');

$finder = sfPropelFinder::from('Comment')->with('Article', 'Category');
$comment = $finder->findOne();
$t->is($finder->getLatestQuery(), $sql, 'with() accepts several arguments, so you don\'t need to call it several times');

$t->diag('withI18n()');

CommentPeer::doDeleteAll();
ArticlePeer::doDeleteAll();
ArticleI18nPeer::doDeleteAll();
$article1 = new Article();
$article1->setTitle('aaa');
$article1->setCulture('en');
$article1->setContent('english content');
$article1->setCulture('fr');
$article1->setContent('contenu français');
$article1->save();

$baseSQL = 'SELECT article.ID, article.TITLE, article.CATEGORY_ID, article_i18n.CONTENT, article_i18n.ID, article_i18n.CULTURE FROM article INNER JOIN article_i18n ON (article.ID=article_i18n.ID) ';
sfContext::getInstance()->getUser()->setCulture('en');
$finder = sfPropelFinder::from('Article')->withI18n();
$article = $finder->findOne();
$query = $finder->getLatestQuery();
$t->is($query, $baseSQL . 'WHERE article_i18n.CULTURE=\'en\' LIMIT 1', 'withI18n() hydrates the related I18n object with a culture taken from the user object');
$t->is($article->getContent(), 'english content', 'withI18n() considers the current user culture for hydration');
$t->is(Propel::getConnection()->getLastExecutedQuery(), $query, 'withI18n() hydrates the i18n object so that no further query is necessary');

sfContext::getInstance()->getUser()->setCulture('fr');
$finder = sfPropelFinder::from('Article')->withI18n();
$article = $finder->findOne();

$t->is($finder->getLatestQuery(), $baseSQL . 'WHERE article_i18n.CULTURE=\'fr\' LIMIT 1', 'withI18n() hydrates the related I18n object with a culture taken from the user object');
$t->is($article->getContent(), 'contenu français', 'withI18n() considers the current user culture for hydration');

sfContext::getInstance()->getUser()->setCulture('fr');
$article = sfPropelFinder::from('Article')->
  withI18n('en')->
  findOne();
$t->is($article->getContent(), 'english content', 'withI18n() accepts a culture parameter to override the user culture');

sfContext::getInstance()->getUser()->setCulture('en');
$finder = sfPropelFinder::from('Article')->with('I18n');
$article = $finder->findOne();
$t->is($finder->getLatestQuery(), $baseSQL . 'WHERE article_i18n.CULTURE=\'en\' LIMIT 1', 'with(\'I18n\') is a synonym for withI18n()');
$finder = sfPropelFinder::from('Article')->with('i18n');
$article = $finder->findOne();
$t->is($finder->getLatestQuery(), $baseSQL . 'WHERE article_i18n.CULTURE=\'en\' LIMIT 1', 'with(\'i18n\') is a synonym for withI18n()');

$t->diag('withColumn()');

ArticlePeer::doDeleteAll();
CommentPeer::doDeleteAll();

$article1 = new Article();
$article1->setTitle('bbbbb');
$article1->setCategory($category1);
$article1->save();
$author1 = new Author();
$author1->setName('John');
$author1->save();
$comment = new Comment();
$comment->setContent('foo');
$comment->setArticleId($article1->getId());
$comment->setAuthor($author1);
$comment->save();

$comment = sfPropelFinder::from('Comment')->
  join('Article')->
  findOne();
try
{
  $comment->getColumn('Article.Title');
  $t->fail('getColumn() is not available as long as you don\'t add a column with withColumn()');
}
catch(Exception $e)
{
  $t->pass('getColumn() is not available as long as you don\'t add a column with withColumn()');
}

$finder = sfPropelFinder::from('Comment')->
  join('Article')->
  withColumn('Article.Title');
$comment = $finder->findOne();
$t->is($comment->getColumn('Article.Title'), 'bbbbb', 'Additional columns added with withColumn() are stored in the object and can be retrieved with getColumn()');
$t->is($finder->getLatestQuery(), 'SELECT comment.ID, comment.CONTENT, comment.ARTICLE_ID, comment.AUTHOR_ID, article.TITLE AS "Article.Title" FROM comment INNER JOIN article ON (comment.ARTICLE_ID=article.ID) LIMIT 1', 'Columns added with withColumn() can contain a dot (and are then escaped with double quotes in SQL)');

$comment = sfPropelFinder::from('Comment')->
  withColumn('Article.Title')->
  findOne();
$t->is($comment->getColumn('Article.Title'), 'bbbbb', 'If withColumn() is called on a related object column with no join on this class, the finder adds the join automatically');

$comment = sfPropelFinder::from('Comment')->
  join('Article')->
  withColumn('Article.Title', 'ArticleTitle')->
  findOne();
$t->is($comment->getColumn('ArticleTitle'), 'bbbbb', 'withColumn() second parameter serves as a column alias');

if (method_exists('ColumnMap', 'getCreoleType'))
{
  // Propel1.2
  $comment = sfPropelFinder::from('Comment')->
    join('Article')->
    withColumn('Article.Title', 'ArticleTitle', 'int')->
    findOne();
  $t->is($comment->getColumn('ArticleTitle'), '0', 'withColumn() third parameter serves as a type caster (only with Propel 1.2)');
}
else
{
  $t->skip('withColumn() third parameter serves as a type caster (only with Propel 1.2)');
}

$comment = sfPropelFinder::from('Comment')->
  join('Article')->join('Author')->
  withColumn('Article.Title')->
  withColumn('Author.Name')->
  findOne();
$t->is($comment->getColumn('Article.Title'), 'bbbbb', 'withColumn() can be called several times');
$t->is($comment->getColumn('Author.Name'), 'John', 'withColumn() can be called several times');

$comment = sfPropelFinder::from('Comment')->
  join('Article')->with('Author')->
  withColumn('Article.Title')->
  findOne();
$t->is($comment->getColumn('Article.Title'), 'bbbbb', 'Columns added with withColumn() live together well with related objects added with with()');
$t->is($comment->getAuthor()->getName(), 'John', 'Related objects added with with() live together well with columns added with withColumn()');

$article = sfPropelFinder::from('Article')->
  join('Comment')->
  groupBy('Article.Id')->
  withColumn('COUNT(Comment.Id)', 'NbComments')->
  findOne();
$t->is($article->getColumn('NbComments'), '1', 'withColumn() accepts complex SQL calculations as additional column');

$finder = sfPropelFinder::from('Article')->
  join('Comment')->
  groupBy('Article.Id')->
  withColumn('COUNT(Comment.Id)', 'NbComments')->
  orderBy('NbComments');
$article = $finder->findOne();
$t->is($finder->getLatestQuery(), 'SELECT article.ID, article.TITLE, article.CATEGORY_ID, COUNT(comment.ID) AS NbComments FROM article INNER JOIN comment ON (article.ID=comment.ARTICLE_ID) GROUP BY article.ID ORDER BY NbComments ASC LIMIT 1', 'Columns added with withColumn() can be used for sorting');

$t->diag('sfPropelFinder::with() issues with object finders classes');
class ArticleFinder extends sfPropelFinder
{
  protected $class = 'Article';
}

$finder = new ArticleFinder;
try
{
  $finder->join('Category')->find();
  $t->pass('Relations lookup work also on finder children objects');
}
catch (Exception $e)
{
  $t->fail('Relations lookup work also on finder children objects');
}
try
{
  $finder->with('Category')->find();
  $t->pass('Relations lookup work also on finder children objects');
}
catch (Exception $e)
{
  $t->fail('Relations lookup work also on finder children objects');
}

$t->diag('sfPropelFinder::with() and left joins');

CommentPeer::doDeleteAll();
ArticlePeer::doDeleteAll();
CategoryPeer::doDeleteAll();

$category1 = new Category();
$category1->setName('cat1');
$category1->save();
$article1 = new Article();
$article1->setTitle('aaa');
$article1->setCategory($category1);
$article1->save();
$article2 = new Article();
$article2->setTitle('bbb');
$article2->save();

$article = sfPropelFinder::from('Article')->leftJoin('Category')->with('Category')->findLast();
$t->isa_ok($article->getCategory(), 'NULL', 'In a left join using with(), empty related objects are not hydrated');

$t->diag('sfPropelFinder::addJoin() not being redirected to Criteria');
try
{
  $article = sfPropelFinder::from('Article')->addJoin(ArticlePeer::CATEGORY_ID, CategoryPeer::ID, Criteria::LEFT_JOIN)->findLast();
  $t->pass('addJoin() is properly redirected to the Criteria object');
}
catch(Exception $e)
{
  $t->fail('addJoin() is properly redirected to the Criteria object');
}

$t->diag('sfPropelFinder::join() called several times');

try
{
  sfPropelFinder::from('Article')->join('Category')->join('Category')->count();
  $t->pass('sfPropelFinder::with() can be used with auto joins');
}
catch(Exception $e)
{
  $t->fail('sfPropelFinder::with() can be used with auto joins');
  echo $e->getMessage();
}

$t->diag('sfPropelFinder::withColumn() on calculated columns with decimals');
$finder = sfPropelFinder::from('Article');
try
{
  $finder->withColumn('COUNT(Comment.Id) * 1.5', 'foo')->findOne();
  $t->pass('withColumn() doesn\'t transform decimal numbers');
}
catch(Exception $e)
{
  $t->fail('withColumn() doesn\'t transform decimal numbers');
}

$t->diag('sfPropelFinder::with() does not fail with two left joins and missing related objects');

CommentPeer::doDeleteAll();
ArticlePeer::doDeleteAll();
Articlei18nPeer::doDeleteAll();
AuthorPeer::doDeleteAll();

$article1 = new Article();
$article1->setTitle('aaa');
$article1->save();
$comment1 = new Comment();
$comment1->setArticleId($article1->getId());
$comment1->save();
$author1 = new Author();
$author1->setName('auth1');
$author1->save();
$comment2 = new Comment();
$comment2->setArticleId($article1->getId());
$comment2->setAuthor($author1);
$comment2->save();

$finder = sfPropelFinder::from('Comment')->
  leftJoin('Author')->with('Author')->
  leftJoin('Article')->with('Article');
$comments = $finder->find();
$latestQuery = $finder->getLatestQuery();
$t->is($comments[0]->getAuthor(), null, 'First object has no author');
$t->is(Propel::getConnection()->getLastExecutedQuery(), $latestQuery, 'Related hydration occurred correctly');
$t->isnt($comments[0]->getArticle(), null, 'First object has an article');
$t->is(Propel::getConnection()->getLastExecutedQuery(), $latestQuery, 'Related hydration occurred correctly');
$t->isnt($comments[1]->getAuthor(), null, 'Second object has an author');
$t->is(Propel::getConnection()->getLastExecutedQuery(), $latestQuery, 'Related hydration occurred correctly');
$t->isnt($comments[1]->getArticle(), null, 'Second object has an article');
$t->is(Propel::getConnection()->getLastExecutedQuery(), $latestQuery, 'Related hydration occurred correctly');