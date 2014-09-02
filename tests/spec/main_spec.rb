require 'httparty'
require 'nokogiri'
require 'mysql'

class Http
  include HTTParty
  base_uri 'http://localhost:8910/'
  follow_redirects false

  def self::timed_post(*args)
    t = Time.now
    ret = self::post(*args)
    elapsed = Time.now - t
    puts
    puts "#{args} - #{elapsed}s"
    return ret
  end
end

# Takes a response, outputs the cookies as they should appear in the Cookie header of a request
def extract_cookies(response)
  response.headers.get_fields('Set-Cookie').map {|a| a.split(';')[0]}.join('; ')
end

RSpec.configure do |config|
  config.expect_with :rspec do |c|
    c.syntax = [:expect, :should]
  end
end

describe "OAuth2Server" do
  before :all do
    @mysql = Mysql.new('localhost', 'root', nil, 'oauth2servertest')

    # Get WP if we don't already have it
    system("test -f latest.zip || wget http://wordpress.org/latest.zip").should be_truthy
    system("test -d wordpress || unzip latest.zip").should be_truthy
    system("rm -rf wordpress/wp-content/plugins/oauth2-server && mkdir -p wordpress/wp-content/plugins/oauth2-server").should be_truthy
    %w[oauth2-server.php lib vendor.phar].each do |f|
      system("ln -s ../../../../../#{f} wordpress/wp-content/plugins/oauth2-server/").should be_truthy
    end

    # Set up DB
    @mysql.query("DROP DATABASE IF EXISTS oauth2servertest")
    @mysql.query("CREATE DATABASE oauth2servertest")
    @mysql.query("USE oauth2servertest")
    system("rm -f wordpress/wp-config.php").should be_truthy
    system("echo 'define(\"OAUTH2_SERVER_TEST_NONCE_OVERRIDE\", \"sudo\");' | wp --path=wordpress/ core config --dbname=oauth2servertest --dbuser=root --extra-php").should be_truthy
    system("wp --path=wordpress/ core install --url=http://localhost:8910/ --title=Test --admin_user=admin --admin_email=tom@dxw.com --admin_password=foobar").should be_truthy
    system("wp --path=wordpress/ plugin activate oauth2-server").should be_truthy
    @mysql.query("INSERT INTO wp_options SET option_name='options_client_applications', option_value='2'")
    @mysql.query("INSERT INTO wp_options SET option_name='options_client_applications_0_client_id', option_value='123'")
    @mysql.query("INSERT INTO wp_options SET option_name='options_client_applications_0_client_secret', option_value='456'")
    @mysql.query("INSERT INTO wp_options SET option_name='options_client_applications_0_name', option_value='Test application 1'")
    @mysql.query("INSERT INTO wp_options SET option_name='options_client_applications_0_redirect_uri', option_value='http://abc/happy'")
    @mysql.query("INSERT INTO wp_options SET option_name='options_client_applications_1_client_id', option_value='456'")
    @mysql.query("INSERT INTO wp_options SET option_name='options_client_applications_1_client_secret', option_value='789'")
    @mysql.query("INSERT INTO wp_options SET option_name='options_client_applications_1_name', option_value='Test application 2'")
    @mysql.query("INSERT INTO wp_options SET option_name='options_client_applications_1_redirect_uri', option_value='http://def/happy'")
    @mysql.query("UPDATE wp_users SET display_name='C. lupus'")

    # Start WP
    @wp_proc = fork do
      exec 'php -d sendmail_path=/bin/false -S localhost:8910 -t wordpress/'
    end
    Process.detach(@wp_proc)
    sleep(5)

    # Store the test cookie
    response = Http::get('/wp-login.php')
    response.response.code.should == '200'
    @cookies = extract_cookies(response)

    # Log in
    response = Http::post(
      '/wp-login.php', 
      body: {
        log: 'admin',
        pwd: 'foobar',
      },
      headers: {'Cookie' => @cookies},
    )
    response.response.code.should == '302'
    @cookies = extract_cookies(response)

    # Verify that we've stored the cookies correctly
    response = Http::get(
      '/wp-admin/', 
      headers: {'Cookie' => @cookies},
    )
    response.response.code.should == '200'
  end

  after :all do
    # Stop WP
    Process.kill('TERM', @wp_proc)
    Process.wait(@wp_proc)
  end

  # Mega-test

  describe "everything" do
    it "works" do
      # Grab the nonce

      response = Http::get(
        '/wp-admin/admin-ajax.php?action=oauth2-auth&access_type=&approval_prompt=&client_id=123&redirect_uri=http%3A%2F%2Fabc%2Fhappy&response_type=code&scope=http%3A%2F%2Flocalhost:8910%2F&state=',
        headers: {'Cookie' => @cookies},
      )
      response.response.code.should == '302'

      expected = URI.parse('http://abc/happy')
      actual = URI.parse(response.headers['Location'])

      actual.path.should == expected.path
      actual.scheme.should == expected.scheme
      actual.host.should == expected.host

      query = actual.query.split('&').map{|a|a.split('=')}.reduce({}){|a,b| a.to_h.update({b[0] => b[1]})}
      query['code'].should be_a String

      # Have the client app make a request

      code = query['code']

      response = Http::timed_post(
        '/wp-admin/admin-ajax.php?action=oauth2-token',
        body: {
          client_id: '123',
          client_secret: '456',
          code: code,
          grant_type: 'authorization_code',
          redirect_uri: 'http://abc/happy',
          scope: 'http://localhost:8910/',
        },
        # No cookies here
      )
      response.response.code.should == '200'
      response.body.should be_a String
      body = JSON.parse(response.body)

      body['access_token'].should be_a String
      body['token_type'].should == 'Bearer'
      body['expires'].should be_a Integer
      body['expires_in'].should be_a Integer

      body['information'].should be_a Hash
      body['information']['email'].should == 'tom@dxw.com'
      body['information']['display_name'].should == 'C. lupus'
    end
  end

  # Actual unit tests

  describe "approve/deny form (which is now just a redirect)" do
    it "stores an access code and redirects" do
      # Make this request first because otherwise $server isn't set
      response = Http::get(
        '/wp-admin/admin-ajax.php?action=oauth2-auth&access_type=&approval_prompt=&client_id=123&redirect_uri=http%3A%2F%2Fabc%2Fhappy&response_type=code&scope=http%3A%2F%2Flocalhost:8910%2F&state=',
        headers: {'Cookie' => @cookies},
      )

      # Test redirect
      response.response.code.should == '302'

      expected = URI.parse('http://abc/happy')
      actual = URI.parse(response.headers['Location'])

      actual.path.should == expected.path
      actual.scheme.should == expected.scheme
      actual.host.should == expected.host

      query = actual.query.split('&').map{|a|a.split('=')}.reduce({}){|a,b| a.to_h.update({b[0] => b[1]})}
      query['code'].should be_a String

      # Test storing access code

      result = @mysql.query("SELECT * FROM wp_oauth2_server_auth_codes WHERE auth_code='%s'" % [query['code']])

      result.num_rows.should == 1
    end

    # http://www.oauthsecurity.com/#leaking-access_tokensigned_request-with-an-open-redirect
    it "ignores the redirect_uri parameter" do
      # Make this request first because otherwise $server isn't set
      response = Http::get(
        # redirect_uri=http://abc/malicious
        '/wp-admin/admin-ajax.php?action=oauth2-auth&access_type=&approval_prompt=&client_id=123&redirect_uri=http%3A%2F%2Fabc%2Fmalicious&response_type=code&scope=http%3A%2F%2Flocalhost:8910%2F&state=',
        headers: {'Cookie' => @cookies},
      )

      # Test redirect
      response.response.code.should == '302'

      expected = URI.parse('http://abc/happy')
      actual = URI.parse(response.headers['Location'])

      actual.path.should == expected.path
      actual.scheme.should == expected.scheme
      actual.host.should == expected.host
    end
  end

  describe "access token request" do
    it "responds with user data" do
      code = 'Chiroptera'
      @mysql.query("INSERT INTO wp_oauth2_server_sessions SET client_id='456', owner_type='user', owner_id=(SELECT ID FROM wp_users WHERE user_email='tom@dxw.com')")
      session_id = @mysql.insert_id
      @mysql.query("INSERT INTO wp_oauth2_server_auth_codes SET session_id=%d, auth_code='%s', expire_time=99999999999999999" % [session_id, code])

      response = Http::timed_post(
        '/wp-admin/admin-ajax.php?action=oauth2-token',
        body: {
          client_id: '456',
          client_secret: '789',
          code: code,
          grant_type: 'authorization_code',
          redirect_uri: 'http://def/happy',
          scope: 'http://localhost:8910/',
        },
        # No cookies here
      )
      response.response.code.should == '200'
      response.body.should be_a String
      body = JSON.parse(response.body)

      body['access_token'].should be_a String
      body['token_type'].should == 'Bearer'
      body['expires'].should be_a Integer
      body['expires_in'].should be_a Integer
      body['refresh_token'].should be_a String

      body['information'].should be_a Hash
      body['information']['email'].should == 'tom@dxw.com'
      body['information']['display_name'].should == 'C. lupus'
    end

    it "provides and stores a refresh token" do
      code = 'hymenoptera'
      @mysql.query("INSERT INTO wp_oauth2_server_sessions SET client_id='456', owner_type='user', owner_id=(SELECT ID FROM wp_users WHERE user_email='tom@dxw.com')")
      session_id = @mysql.insert_id
      @mysql.query("INSERT INTO wp_oauth2_server_auth_codes SET session_id=%d, auth_code='%s', expire_time=99999999999999999" % [session_id, code])

      response = Http::timed_post(
        '/wp-admin/admin-ajax.php?action=oauth2-token',
        body: {
          client_id: '456',
          client_secret: '789',
          code: code,
          grant_type: 'authorization_code',
          redirect_uri: 'http://def/happy',
          scope: 'http://localhost:8910/',
        },
        # No cookies here
      )
      response.response.code.should == '200'
      response.body.should be_a String
      body = JSON.parse(response.body)

      body['refresh_token'].should be_a String

      result = @mysql.query("SELECT * FROM wp_oauth2_server_refresh_tokens WHERE refresh_token='%s'" % [body['refresh_token']])

      result.num_rows.should == 1
    end

    it "responds appropriately to refresh tokens" do
      refresh_token = 'Aves'
      @mysql.query("INSERT INTO wp_oauth2_server_sessions SET client_id='456', owner_type='user', owner_id=(SELECT ID FROM wp_users WHERE user_email='tom@dxw.com')")
      session_id = @mysql.insert_id
      @mysql.query("INSERT INTO wp_oauth2_server_access_tokens SET session_id=%d, access_token='Dinosauria'" % [session_id])
      access_token_id = @mysql.insert_id
      @mysql.query("INSERT INTO wp_oauth2_server_refresh_tokens SET access_token_id=%d, refresh_token='%s', client_id='456'" % [access_token_id, refresh_token])

      response = Http::timed_post(
        '/wp-admin/admin-ajax.php?action=oauth2-token',
        body: {
          client_id: '456',
          client_secret: '789',
          refresh_token: refresh_token,
          grant_type: 'refresh_token',
          redirect_uri: 'http://def/happy',
          scope: 'http://localhost:8910/',
        },
        # No cookies here
      )
      response.response.code.should == '200'
      response.body.should be_a String
      body = JSON.parse(response.body)

      body['access_token'].should be_a String

      # Rotate refresh tokens
      body['refresh_token'].should be_a String
    end

    # http://www.oauthsecurity.com/#replay-attack
    it "rejects replay attacks" do
      code = 'Pterodon'
      @mysql.query("INSERT INTO wp_oauth2_server_sessions SET client_id='456', owner_type='user', owner_id=(SELECT ID FROM wp_users WHERE user_email='tom@dxw.com')")
      session_id = @mysql.insert_id
      @mysql.query("INSERT INTO wp_oauth2_server_auth_codes SET session_id=%d, auth_code='%s', expire_time=99999999999999999" % [session_id, code])

      response = Http::timed_post(
        '/wp-admin/admin-ajax.php?action=oauth2-token',
        body: {
          client_id: '456',
          client_secret: '789',
          code: code,
          grant_type: 'authorization_code',
          redirect_uri: 'http://def/happy',
          scope: 'http://localhost:8910/',
        },
        # No cookies here
      )
      response.response.code.should == '200'
      response.body.should be_a String
      body = JSON.parse(response.body)

      body['access_token'].should be_a String
      body['token_type'].should == 'Bearer'

      # Replay

      response = Http::timed_post(
        '/wp-admin/admin-ajax.php?action=oauth2-token',
        body: {
          client_id: '456',
          client_secret: '789',
          code: code,
          grant_type: 'authorization_code',
          redirect_uri: 'http://def/happy',
          scope: 'http://localhost:8910/',
        },
        # No cookies here
      )
      response.response.code.should == '500'
      response.body.should be_a String
      body = JSON.parse(response.body)

      body['error'].should be_truthy
      body['message'].should == 'invalid auth code'
    end

    it "doesn't raise exceptions" do
      code = 'Pterodon'
      @mysql.query("INSERT INTO wp_oauth2_server_sessions SET client_id='456', owner_type='user', owner_id=(SELECT ID FROM wp_users WHERE user_email='tom@dxw.com')")
      session_id = @mysql.insert_id
      @mysql.query("INSERT INTO wp_oauth2_server_auth_codes SET session_id=%d, auth_code='%s', expire_time=99999999999999999" % [session_id, code])

      response = Http::timed_post(
        '/wp-admin/admin-ajax.php?action=oauth2-token',
        body: {
          client_id: '456',
          client_secret: '789',
          code: code,
          grant_type: 'basilosaurus',
          redirect_uri: 'http://def/happy',
          scope: 'http://localhost:8910/',
        },
        # No cookies here
      )
      response.body.should be_a String
      response.body.should_not include 'Fatal error: Uncaught exception'
      response.response.code.should == '500'
      body = JSON.parse(response.body)

      body['error'].should be_truthy
      body['message'].should == 'invalid grant type'
    end
  end
end
