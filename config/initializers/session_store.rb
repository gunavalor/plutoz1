# Be sure to restart your server when you modify this file.

# Your secret key for verifying cookie session data integrity.
# If you change this key, all old sessions will become invalid!
# Make sure the secret is at least 30 characters and all random, 
# no regular words or you'll be exposed to dictionary attacks.
ActionController::Base.session = {
  :key         => '_plutoz_session',
  :secret      => 'cebd50d17f2fa0bc445c4886ba80c50c5913522453beb9a19c3dee72254ef45b617f484448388728a14b76773af8ac67935e55aa550e0742b677ec981f39b3bc'
}

# Use the database for sessions instead of the cookie-based default,
# which shouldn't be used to store highly confidential information
# (create the session table with "rake db:sessions:create")
# ActionController::Base.session_store = :active_record_store
