#!/bin/env ruby
puts "Renaming in "

require 'optparse'

old_name = 'examus2'
package_name = 'examus2'

OptionParser.new do |opt|
  opt.on('--rename PACKAGE_NAME') { |o| package_name = o }
end.parse!

puts options

files = Dir.glob('**/*', File::FNM_DOTMATCH)
pp files

version
output_dir = "releases/#{package_name}-#{version}"

puts "Output dir #{output_dir}"
