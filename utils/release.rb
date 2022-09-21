#!/bin/env ruby
require 'optparse'
require 'fileutils'

dry = false
force = false
verbose = true
old_name = 'examus2'
package_name = 'examus2'

ignore = [
    'releases',
    'utils',
]

OptionParser.new do |opt|
  opt.on('--name PACKAGE_NAME') { |o| package_name = o }
  opt.on('--dry'){ |dry| dry = o }
  opt.on('-f', '--force'){ |o| force = o }
  opt.on('-v', '--verbose'){ |o| verbose = o }

end.parse!

files = Dir.glob('**/*')
files.reject! do |f|
  ignore.any?{ |i| f.start_with?i }
end

code_pattern = Regexp.new old_name, Regexp::IGNORECASE
code_renames = {
  old_name.capitalize => package_name.capitalize,
  old_name => package_name
}
text_pattern = Regexp.new '(examus|экзамус)', Regexp::IGNORECASE
text_renames = {
    'Examus' => package_name.capitalize,
    'EXAMUS' => package_name.upcase,
    'examus' => package_name,
    'Экзамус' => package_name.capitalize,
    'экзамус' => package_name,
}

version_regex = /plugin->version\s*=\s*(\d+)/
version_match = File.read('version.php').match version_regex

throw "Cant find version in version.php" unless version_match

version = version_match[1]

output_dir = "releases/#{package_name}-#{version}/"

if File.exists?(output_dir)
  throw "Output dir already exists, use -f flag" if !force

  puts "Cleaning old files from #{output_dir}"
  FileUtils::rm_rf output_dir
end

puts "Creating output dir #{output_dir}"
FileUtils::mkdir_p output_dir
puts "Copying new files"
files.each do |src|
  rename = package_name != old_name
  dst = rename ? src.gsub(code_pattern, code_renames) : src

  puts(src == dst ? src : "#{src} -> #{dst}") if verbose

  if File.directory?(src)
    FileUtils::mkdir output_dir + dst
  else
    FileUtils::cp src, output_dir + dst

    if rename
      content = File.read src
      matches = content.scan Regexp.new("#{code_pattern}[^\s\._;\"\'\n]*", Regexp::IGNORECASE)
      matches += content.scan text_pattern

      if matches.count > 0
        puts "Replacing matches: #{matches.flatten.uniq}"
        content.gsub code_pattern, code_renames
        content.gsub text_pattern, text_renames

        File.write output_dir+dst, content
      end
    end
  end
end
