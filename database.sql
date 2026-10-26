
CREATE DATABASE IF NOT EXISTS gymnasium_db;
USE gymnasium_db;

CREATE TABLE Attendees (
    AttendeeID INT AUTO_INCREMENT PRIMARY KEY,
    StudentNumber VARCHAR(30) NOT NULL UNIQUE,
    FullName VARCHAR(100) NOT NULL,
    Course VARCHAR(50),
    YearLevel INT,
    ContactNumber VARCHAR(20),
    StudentPassword VARCHAR(255) NOT NULL
);

CREATE TABLE admin_table (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    FullName VARCHAR(100) NOT NULL,
    Username VARCHAR(50) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    Role VARCHAR(20) NOT NULL,
    AccountStatus VARCHAR(20) NOT NULL DEFAULT 'Active',
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT Users_Role
        CHECK (Role IN ('Admin', 'Staff')),

    CONSTRAINT Users_AccountStatus
        CHECK (AccountStatus IN ('Active', 'Inactive'))
);

CREATE TABLE Events (
    EventID INT AUTO_INCREMENT PRIMARY KEY,
    EventName VARCHAR(150) NOT NULL,
    EventDescription VARCHAR(500),
    EventDate DATE NOT NULL,
    Venue VARCHAR(100) DEFAULT 'Gymnasium',
    StartTime TIME NOT NULL,
    EndTime TIME NOT NULL,
    EventStatus VARCHAR(20) DEFAULT 'Scheduled'
);

CREATE TABLE Attendance (
    AttendanceID INT AUTO_INCREMENT PRIMARY KEY,
    AttendeeID INT NOT NULL,
    EventID INT NOT NULL,
    TimeIn DATETIME DEFAULT CURRENT_TIMESTAMP,
    TimeOut DATETIME NULL,
    AttendanceStatus VARCHAR(20) DEFAULT 'Present',

    FOREIGN KEY (AttendeeID)
        REFERENCES Attendees(AttendeeID),

    FOREIGN KEY (EventID)
        REFERENCES Events(EventID),

    UNIQUE (AttendeeID, EventID)
);

INSERT INTO admin_table
    (FullName, Username, PasswordHash, Role, AccountStatus)
VALUES
    (
        'Administrator',
        'admin',
        '$2y$12$Bm3XEWPkbt8e1FFdKFS4U.VVIbeyClk72Q7dncqb.xmzDc9ldqZUW',
        'Admin',
        'Active'
    );

INSERT INTO Attendees
    (StudentNumber, FullName, Course, YearLevel, ContactNumber, StudentPassword)
VALUES
    ('8240824', 'Lou Marie Tamboboy', 'BSIT', 3, '09352394582', ''),
    ('8940894', 'Joven Peppito', 'BSIT', 3, '09181234567', ''),
    ('8946956', 'Reshiele Punay', 'BSIT', 3, '09069607393', '');

INSERT INTO Events
    (EventName, EventDescription, EventDate,
     Venue, StartTime, EndTime)
VALUES
    (
        'Foundation Day',
        'School foundation celebration',
        '2026-11-15',
        'School Gymnasium',
        '08:00:00',
        '17:00:00'
    ),
    (
        'Sports Fest',
        'Annual sports festival',
        '2026-11-20',
        'School Gymnasium',
        '07:00:00',
        '18:00:00'
    );
