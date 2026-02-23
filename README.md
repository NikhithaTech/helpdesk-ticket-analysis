# Helpdesk Ticket Analysis and Resolution Tracker

End-to-end Helpdesk Ticket Management System built using PHP, MySQL (XAMPP), and Power BI for real-time analytics and reporting.

This project captures support tickets, stores them in a structured database, and visualizes key performance metrics through interactive dashboards.

---

## 📌 Project Overview

Modern organizations require structured helpdesk systems to efficiently manage IT support requests. Many SMEs rely on emails or spreadsheets, which leads to poor tracking, lack of visibility, and no analytical insights.

This system provides:

- Structured ticket logging through a PHP interface  
- Secure storage using MySQL (via XAMPP)  
- Real-time analytics using Power BI dashboards  
- SLA monitoring and performance tracking  

It bridges the gap between ticket operations and data-driven decision-making.

---

## 🛠️ Tech Stack

- **Frontend:** PHP
- **Database:** MySQL (XAMPP)
- **Server:** Apache (XAMPP)
- **Analytics & Visualization:** Microsoft Power BI
- **Development Tool:** Visual Studio Code

---

## 🏗️ System Architecture

The system follows a three-tier architecture:

1. **Presentation Layer**
   - PHP-based ticket submission form
   - User interface for ticket tracking

2. **Application Layer**
   - PHP logic for validation and workflow management
   - Ticket lifecycle: Open → In Progress → Resolved

3. **Database Layer**
   - MySQL relational database
   - Normalized schema with indexed fields

4. **Analytics Layer**
   - Power BI connected directly to MySQL
   - Interactive dashboards with drill-down analysis

---

## 🔄 Workflow

1. User submits ticket via PHP form  
2. Data is validated and stored in MySQL  
3. IT staff update ticket status  
4. Power BI retrieves data from database  
5. Dashboards display trends, SLA compliance, and workload metrics  

---

## 📊 Dashboard Features

- Ticket count by category  
- Ticket priority distribution  
- Status tracking (Open / In Progress / Resolved)  
- Average resolution time  
- SLA compliance monitoring  
- Staff workload distribution  
- Department-wise ticket trends  

---

## 📁 Project Structure
helpdesk-ticket-analysis/
│
├── README.md
├── database/
│   └── schema.sql
├── php/
│   └── index.php
├── powerbi/
│   └── dashboard.pbix
---

## 🎯 Key Objectives

- Streamline helpdesk ticket logging  
- Improve transparency and accountability  
- Enable data-driven IT decision making  
- Provide affordable solution for SMEs  
- Reduce downtime caused by unresolved issues  

---

## 🚀 How to Run the Project

1. Install XAMPP and start Apache + MySQL  
2. Import `schema.sql` into MySQL via phpMyAdmin  
3. Place PHP files inside XAMPP `htdocs` folder  
4. Open browser and run `localhost/php/index.php`  
5. Connect Power BI to MySQL database  
6. Load dashboard file (.pbix)  

---

## 🔐 Security Features

- Role-based access control  
- Input validation to prevent SQL injection  
- Structured database with proper indexing  
- Timestamp tracking for accountability  

---

## 📈 Future Enhancements

- AI-based ticket assignment  
- Email notification integration  
- Cloud deployment  
- Mobile interface  
- Chatbot integration  

---

## 📚 Project Significance

This project demonstrates integration of database management, backend development, and business intelligence tools to create a scalable IT service management solution suitable for SMEs.

---

## 👩‍💻 Author

 Nikhitha.R
