# This file should contain all the record creation needed to seed the database with its default values.
# The data can then be loaded with the rake db:seed (or created alongside the db with db:setup).
#
# Examples:
#   
#   cities = City.create([{ :name => 'Chicago' }, { :name => 'Copenhagen' }])
#   Major.create(:name => 'Daley', :city => cities.first)
    Category.delete_all()
categories = Category.create([{ :catid => '1', :catname => 'Accessories' }, {:catid => '2', :catname => 'Arts and Crafts'}, {:catid => '3', :catname => 'Automotive'}, {:catid => '4', :catname => 'Beauty'}, {:catid => '5', :catname => 'Books'}, {:catid => '6', :catname => 'Computers'}, {:catid => '7', :catname => 'Education'}, {:catid => '8', :catname => 'Electronics'}, {:catid => '9', :catname => 'Entertainment'}, {:catid => '10', :catname => 'Environment'}, {:catid => '11', :catname => 'Finance'}, {:catid => '12', :catname => 'Flowers'}, {:catid => '13', :catname => 'Foods'}, {:catid => '14', :catname => 'General retail'}, {:catid => '15', :catname => 'Gifts'}, {:catid => '16', :catname => 'Health'}, {:catid => '17', :catname => 'Home'}, {:catid => '18', :catname => 'Jobs'}, {:catid => '19', :catname => 'Kids and Babies'}, {:catid => '20', :catname => 'Luxury'}, {:catid => '21', :catname => 'Mens Apparel'}, {:catid => '22', :catname => 'Non-Profit'}, {:catid => '23', :catname => 'Pets'}, {:catid => '24', :catname => 'Recreational'}, {:catid => '25', :catname => 'Vehicles'}, {:catid => '26', :catname => 'Sports and Outdoors'}, {:catid => '27', :catname => 'Toys and  Gaming'}, {:catid => '28', :catname => 'Travel'}, {:catid => '29', :catname => 'Womens Apparel'}])

