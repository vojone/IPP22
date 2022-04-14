INZIP = *.php Tests *.py readme1.pdf readme2.pdf rozsireni
ZIPNAME = xdvora3o.zip
PTESTS_PATH = Tests/parse-only
ITESTS_PATH =  Tests/int-only
TESTS_PATH =  Tests/both

JEXAMPATH = jexamxml/

zip:
	zip -r $(ZIPNAME) $(INZIP)

test:
	php8.1 test.php --directory=$(TESTS_PATH) --recursive >index.html --jexampath=$(JEXAMPATH)

ptest:
	php8.1 test.php --parse-only --directory=$(PTESTS_PATH) --recursive >index.html --jexampath=$(JEXAMPATH)

itest:
	php8.1 test.php --int-only --directory=$(ITESTS_PATH) --recursive >index.html --jexampath=$(JEXAMPATH)

clean:
	rm -f $(ZIPNAME)