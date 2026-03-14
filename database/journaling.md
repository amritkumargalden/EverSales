Day 1: set up jdk, xampp, tomcat, env variables
Day 2: Created two databases: schema for structure, seed.sql for entering data, build dbconnection utility.
Day 3: skipped
Day 4: skipped
Day 11: 

                Here are all commands used in this conversation:

                Java
                Command	Use
                java -version	Check Java version installed
                & 'C:\Program Files\Java\jdk-25\bin\java.exe' --version	Directly verify JDK when PATH has issues
                Maven
                Command	Use
                mvn -version	Check Maven version
                mvn clean package	Delete old build + compile + package into WAR
                mvn -q validate	Quickly validate pom.xml is correct without building
                MySQL / MariaDB
                Command	Use
                mysql --version	Check MySQL CLI is installed
                mysql -u root -p -e "SELECT VERSION();"	Verify DB server is running and check version
                mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS eversales;"	Create project database
                mysql -u root -p < database/schema.sql	Execute schema (create tables)
                mysql -u root -p < database/seed.sql	Execute seed (insert sample data)
                mysqladmin --protocol=tcp -h 127.0.0.1 -P 3306 ping	Ping MySQL server to check if running
                Tomcat
                Command	Use
                startup.bat	Start Tomcat server
                shutdown.bat	Stop Tomcat server
                version.bat	Print Tomcat version info
                setx CATALINA_HOME "..."	Save Tomcat home path permanently
                setx CATALINA_BASE "..."	Save Tomcat base path permanently
                Chocolatey / winget
                Command	Use
                choco --version	Check Chocolatey is available
                choco search tomcat	Find Tomcat packages
                choco install tomcat10 -y	Install Tomcat 10 via Chocolatey
                winget install --id Oracle.MySQL	Install MySQL via winget
                PowerShell utilities
                Command	Use
                Get-ChildItem -Recurse -File	List all files in project
                Get-Service	List Windows services
                Start-Service -Name ...	Start a Windows service
                Invoke-WebRequest -Uri "http://localhost:8080"	Test if Tomcat is responding
                Expand-Archive -Path ...	Extract a ZIP file
                Claude Sonnet 4.6 • 0.9x

                //start tomcat
$tomcat = "C:\tools\apache-tomcat-10.1.52-windows-x64\apache-tomcat-10.1.52"
$env:CATALINA_HOME = $tomcat
$env:CATALINA_BASE = $tomcat
& "$tomcat\bin\startup.bat"
Start-Sleep -Seconds 3
Invoke-WebRequest "http://localhost:8080" -UseBasicParsing | Select-Object StatusCode

                //stop tomcat
& "$tomcat\bin\shutdown.bat"


open mysqql??
mysql -u root -p

