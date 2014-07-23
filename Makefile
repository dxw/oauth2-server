.PHONY: all test

all: test

test:
	cd tests && rspec spec/*_spec.rb
