.PHONY: all test

all: test

test:
	cd tests && bundle exec rspec spec/*_spec.rb
