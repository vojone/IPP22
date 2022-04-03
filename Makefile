INZIP = *.php Tests *.py readme1.pdf readme2.pdf rozsireni
ZIPNAME = xdvora3o.zip
PTESTS_PATH = Tests/parse-only
ITESTS_PATH =  Tests/int-only

zip:
	zip -r $(ZIPNAME) $(INZIP)

ptest:
	php8.1 test.php --parse-only --directory=$(PTESTS_PATH) --recursive >index.html

itest:
	php8.1 test.php --int-only --directory=$(ITESTS_PATH) --recursive >index.html

clean:
	rm -f $(ZIPNAME)