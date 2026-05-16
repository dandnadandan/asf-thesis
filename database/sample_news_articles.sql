-- Sample News Articles and Announcements for ASF Surveillance System - CALABARZON
-- Insert 5 sample articles covering different categories and statuses

-- Note: Replace author_id with an actual user ID from user_accounts table
-- Default administrator (user_id = 1) is used as author_id

-- 1. News Article - Published
INSERT INTO news_articles (title, slug, excerpt, content, category, status, published_at, author_id, views_count, meta_keywords, meta_description, created_at) VALUES
(
  'CALABARZON ASF Surveillance System Launches Comprehensive Monitoring Program',
  'calabarzon-asf-surveillance-system-launches-comprehensive-monitoring-program',
  'The Department of Agriculture CALABARZON launches an advanced GIS-based surveillance system to monitor and predict African Swine Fever outbreaks across the region.',
  '<h2>CALABARZON ASF Surveillance System Launches Comprehensive Monitoring Program</h2>
  <p>The Department of Agriculture (DA) CALABARZON has officially launched a state-of-the-art GIS-based surveillance system designed to enhance early detection and effective management of African Swine Fever (ASF) outbreaks across the region.</p>
  
  <h3>Key Features of the System</h3>
  <ul>
    <li><strong>Real-time Monitoring:</strong> Continuous tracking of ASF cases and high-risk zones</li>
    <li><strong>Predictive Analytics:</strong> Machine learning algorithms to forecast potential outbreaks</li>
    <li><strong>Environmental Data Integration:</strong> Analysis of temperature, humidity, and rainfall patterns</li>
    <li><strong>Meat Movement Tracking:</strong> Comprehensive monitoring of pork product transportation</li>
  </ul>
  
  <h3>Coverage Areas</h3>
  <p>The system covers all provinces in CALABARZON:</p>
  <ul>
    <li>Cavite</li>
    <li>Laguna</li>
    <li>Batangas</li>
    <li>Rizal</li>
    <li>Quezon</li>
  </ul>
  
  <p>The initiative is part of the national effort to combat ASF and protect the local swine industry, which plays a crucial role in the region\'s economy.</p>',
  'news',
  'published',
  NOW(),
  1,
  150,
  'ASF, African Swine Fever, CALABARZON, surveillance system, monitoring, Department of Agriculture',
  'CALABARZON launches advanced GIS-based ASF surveillance system for real-time monitoring and outbreak prediction',
  NOW()
);

-- 2. Announcement - Published
INSERT INTO news_articles (title, slug, excerpt, content, category, status, published_at, author_id, views_count, meta_keywords, meta_description, created_at) VALUES
(
  'Important Notice: Enhanced Depopulation Protocols in Batangas Province',
  'important-notice-enhanced-depopulation-protocols-batangas-province',
  'All swine farmers and stakeholders in Batangas are required to follow enhanced depopulation protocols effective immediately.',
  '<h2>Important Notice: Enhanced Depopulation Protocols in Batangas Province</h2>
  
  <p><strong>To All Swine Farmers and Stakeholders in Batangas Province:</strong></p>
  
  <p>The Bureau of Animal Industry (BAI), in coordination with the CALABARZON ASF Surveillance System, hereby announces the implementation of enhanced depopulation protocols for affected areas in Batangas Province.</p>
  
  <h3>New Requirements</h3>
  <ol>
    <li>Immediate reporting of suspected ASF cases to local veterinary offices</li>
    <li>Proper documentation and compensation procedures</li>
    <li>Bio-security measures during depopulation activities</li>
    <li>Disposal protocols following environmental regulations</li>
  </ol>
  
  <h3>Compensation Guidelines</h3>
  <p>Affected farmers will receive compensation based on the following criteria:</p>
  <ul>
    <li>Age and weight of depopulated animals</li>
    <li>Proper documentation and reporting</li>
    <li>Compliance with biosecurity protocols</li>
  </ul>
  
  <p><strong>Contact Information:</strong></p>
  <p>For inquiries, please contact the Batangas Provincial Veterinary Office at (043) 723-4567 or email batangas.vet@bai.gov.ph</p>
  
  <p><em>This announcement takes effect immediately.</em></p>',
  'announcement',
  'published',
  NOW(),
  1,
  89,
  'depopulation, Batangas, ASF protocols, compensation, Bureau of Animal Industry',
  'Enhanced depopulation protocols announced for Batangas Province with updated compensation guidelines',
  NOW()
);

-- 3. Guideline - Published
INSERT INTO news_articles (title, slug, excerpt, content, category, status, published_at, author_id, views_count, meta_keywords, meta_description, created_at) VALUES
(
  'ASF Prevention Guidelines for Small-scale Swine Farmers in CALABARZON',
  'asf-prevention-guidelines-small-scale-swine-farmers-calabarzon',
  'Comprehensive guidelines to help small-scale swine farmers in CALABARZON prevent and manage ASF outbreaks on their farms.',
  '<h2>ASF Prevention Guidelines for Small-scale Swine Farmers in CALABARZON</h2>
  
  <h3>Biosecurity Measures</h3>
  
  <h4>Farm Entry Controls</h4>
  <ul>
    <li>Install foot baths with disinfectant at all entry points</li>
    <li>Require all visitors to wear protective clothing and boots</li>
    <li>Limit farm visits and maintain visitor logs</li>
    <li>Prohibit entry of unauthorized vehicles</li>
  </ul>
  
  <h4>Feed and Water Management</h4>
  <ul>
    <li>Use only properly cooked swill (kitchen waste) as feed</li>
    <li>Ensure water sources are protected from contamination</li>
    <li>Avoid feeding uncooked kitchen waste or leftovers</li>
    <li>Store feed in sealed containers to prevent contact with wild animals</li>
  </ul>
  
  <h4>Animal Health Monitoring</h4>
  <ul>
    <li>Conduct daily health checks of all animals</li>
    <li>Watch for signs: high fever, loss of appetite, reddening of skin, difficulty breathing</li>
    <li>Immediately isolate sick animals</li>
    <li>Report suspected cases to local veterinarians immediately</li>
  </ul>
  
  <h3>What to Do If You Suspect ASF</h3>
  <ol>
    <li><strong>Do NOT move animals or products off the farm</strong></li>
    <li>Isolate affected animals immediately</li>
    <li>Contact your local veterinary office or BAI hotline</li>
    <li>Document all observations (symptoms, number of affected animals)</li>
    <li>Follow instructions from veterinary authorities</li>
  </ol>
  
  <h3>Contact Information</h3>
  <p><strong>CALABARZON ASF Hotline:</strong> (049) 123-4567<br>
  <strong>BAI Emergency Hotline:</strong> (02) 8526-5936<br>
  <strong>Email:</strong> asf.calabarzon@bai.gov.ph</p>
  
  <p><em>Remember: Early detection and reporting are crucial in preventing the spread of ASF.</em></p>',
  'guideline',
  'published',
  NOW(),
  1,
  234,
  'ASF prevention, biosecurity, small-scale farmers, guidelines, CALABARZON, swine farming',
  'Comprehensive ASF prevention guidelines for small-scale swine farmers in CALABARZON with biosecurity measures and emergency protocols',
  NOW()
);

-- 4. Update - Published
INSERT INTO news_articles (title, slug, excerpt, content, category, status, published_at, author_id, views_count, meta_keywords, meta_description, created_at) VALUES
(
  'Monthly ASF Situation Update: CALABARZON Region - January 2026',
  'monthly-asf-situation-update-calabarzon-region-january-2026',
  'Latest statistics and updates on ASF situation across CALABARZON provinces for January 2026.',
  '<h2>Monthly ASF Situation Update: CALABARZON Region - January 2026</h2>
  
  <h3>Overview</h3>
  <p>As of January 31, 2026, the CALABARZON ASF Surveillance System has recorded the following statistics across the region.</p>
  
  <h3>Provincial Status</h3>
  
  <table border="1" cellpadding="5" style="width: 100%; border-collapse: collapse;">
    <thead>
      <tr style="background-color: #f0f0f0;">
        <th>Province</th>
        <th>Active Cases</th>
        <th>Resolved Cases</th>
        <th>Depopulation Events</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>Cavite</strong></td>
        <td>3</td>
        <td>15</td>
        <td>8</td>
        <td>Surveillance Zone</td>
      </tr>
      <tr>
        <td><strong>Laguna</strong></td>
        <td>1</td>
        <td>22</td>
        <td>12</td>
        <td>Protected Zone</td>
      </tr>
      <tr>
        <td><strong>Batangas</strong></td>
        <td>5</td>
        <td>18</td>
        <td>10</td>
        <td>Buffer Zone</td>
      </tr>
      <tr>
        <td><strong>Rizal</strong></td>
        <td>0</td>
        <td>8</td>
        <td>3</td>
        <td>Free Zone</td>
      </tr>
      <tr>
        <td><strong>Quezon</strong></td>
        <td>2</td>
        <td>12</td>
        <td>5</td>
        <td>Surveillance Zone</td>
      </tr>
    </tbody>
  </table>
  
  <h3>Key Highlights</h3>
  <ul>
    <li>Total active outbreaks: <strong>11 cases</strong> (down from 18 in December 2025)</li>
    <li>New cases reported this month: <strong>7</strong></li>
    <li>Cases resolved: <strong>75</strong> (cumulative)</li>
    <li>Total depopulation events: <strong>38</strong> (cumulative)</li>
  </ul>
  
  <h3>Trends and Observations</h3>
  <p>The surveillance data indicates a <strong>38% decrease</strong> in active cases compared to the previous month. Rizal province maintains its Free Zone status with zero active cases. Enhanced monitoring continues in Batangas and Cavite where most cases are concentrated.</p>
  
  <h3>Next Steps</h3>
  <ul>
    <li>Continue intensified surveillance in high-risk areas</li>
    <li>Enhance biosecurity measures in buffer zones</li>
    <li>Support affected farmers through compensation programs</li>
    <li>Conduct regular risk zone assessments</li>
  </ul>
  
  <p><em>Data compiled by the CALABARZON ASF Surveillance System. For detailed reports, visit the system dashboard.</em></p>',
  'update',
  'published',
  NOW(),
  1,
  312,
  'ASF update, CALABARZON statistics, January 2026, outbreak data, surveillance zones',
  'Monthly ASF situation update for CALABARZON region with statistics and status for all provinces',
  NOW()
);

-- 5. Alert - Published
INSERT INTO news_articles (title, slug, excerpt, content, category, status, published_at, author_id, views_count, meta_keywords, meta_description, created_at) VALUES
(
  'URGENT: High-Risk Zone Alert - Cavite Province',
  'urgent-high-risk-zone-alert-cavite-province',
  'The CALABARZON ASF Surveillance System issues a high-risk zone alert for several municipalities in Cavite Province.',
  '<h2 style="color: #dc3545;">URGENT: High-Risk Zone Alert - Cavite Province</h2>
  
  <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
    <p><strong>Alert Level:</strong> HIGH RISK<br>
    <strong>Issued:</strong> January 15, 2026<br>
    <strong>Validity:</strong> Until further notice</p>
  </div>
  
  <h3>Affected Areas</h3>
  <p>The following municipalities in Cavite have been classified as <strong>High-Risk Zones</strong> based on recent surveillance data:</p>
  
  <ul>
    <li><strong>Bacoor</strong> - Multiple suspected cases reported</li>
    <li><strong>Imus</strong> - Proximity to confirmed outbreak areas</li>
    <li><strong>Dasmariñas</strong> - Increased depopulation activities</li>
    <li><strong>General Trias</strong> - Elevated environmental risk factors</li>
  </ul>
  
  <h3>Recommended Actions</h3>
  
  <h4>For Swine Farmers</h4>
  <ul>
    <li><strong>IMMEDIATELY:</strong> Enhance biosecurity measures on your farms</li>
    <li>Restrict movement of animals in and out of your premises</li>
    <li>Monitor animals closely for any signs of illness</li>
    <li>Report any suspected cases to authorities immediately</li>
    <li>Cooperate with surveillance teams conducting inspections</li>
  </ul>
  
  <h4>For Meat Traders and Transporters</h4>
  <ul>
    <li>Ensure all meat products have proper health certificates</li>
    <li>Follow designated routes and avoid restricted zones</li>
    <li>Comply with meat movement tracking requirements</li>
    <li>Report suspicious activities or unauthorized transport</li>
  </ul>
  
  <h4>For the General Public</h4>
  <ul>
    <li>Cook pork products thoroughly (internal temperature of 70°C)</li>
    <li>Do not feed kitchen waste to pigs without proper cooking</li>
    <li>Avoid contact with swine farms in affected areas</li>
    <li>Report dead or sick pigs to local authorities</li>
  </ul>
  
  <h3>Emergency Contacts</h3>
  <p><strong>Cavite Provincial Veterinary Office:</strong> (046) 431-2345<br>
  <strong>CALABARZON ASF Hotline:</strong> (049) 123-4567<br>
  <strong>BAI Emergency Hotline:</strong> (02) 8526-5936</p>
  
  <div style="background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0;">
    <p><strong>⚠️ IMPORTANT:</strong> This is an active surveillance alert. Please take all necessary precautions and stay informed through official channels.</p>
  </div>
  
  <p><em>This alert will be updated as the situation develops. Monitor the CALABARZON ASF Surveillance System dashboard for real-time updates.</em></p>',
  'alert',
  'published',
  NOW(),
  1,
  445,
  'ASF alert, high-risk zone, Cavite, urgent, prevention measures, CALABARZON',
  'Urgent high-risk zone alert issued for Cavite Province with recommended actions for farmers and stakeholders',
  NOW()
);
