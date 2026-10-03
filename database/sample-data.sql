-- =====================================================================
--  OPTIONAL sample data (the pets, applications and bookings from the design)
--  Run AFTER schema.sql if you want something to look at while testing.
--  Delete the rows from the admin panel when you start using it for real.
-- =====================================================================

INSERT INTO pets (name, species, breed, age_months, gender, size, shelter_id, intake_date, status, description) VALUES
    ('Biscuit', 'dog',    'Golden Retriever', 24, 'male',   'large',  1, '2026-03-12', 'adopted',   'Friendly and calm, great with children.'),
    ('Luna',    'cat',    'Siamese',          14, 'female', 'small',  2, '2026-06-02', 'available', 'Talkative, likes a quiet home.'),
    ('Coco',    'dog',    'Poodle Mix',       36, 'female', 'medium', 1, '2026-05-19', 'reserved',  'Vaccinated, house trained.'),
    ('Rocky',   'dog',    'French Bulldog',   48, 'male',   'medium', 3, '2026-07-08', 'medical',   'Recovering from a skin infection.'),
    ('Milo',    'cat',    'Tabby',             6, 'male',   'small',  2, '2026-08-21', 'available', 'Playful kitten.'),
    ('Bella',   'dog',    'Labrador',         60, 'female', 'large',  3, '2026-04-30', 'available', 'Needs daily walks.'),
    ('Oreo',    'rabbit', 'Dutch',            12, 'male',   'small',  1, '2026-09-03', 'available', 'Litter trained.'),
    ('Kiwi',    'bird',   'Budgerigar',        8, 'female', 'small',  2, '2026-09-14', 'reserved',  'Sings in the morning.');

INSERT INTO applications (pet_id, applicant_name, applicant_email, applicant_phone, home_type, quiz_score, status, created_at, decided_at) VALUES
    (1, 'Nusrat Jahan',  'nusrat.j@gmail.com',      '+880 1711-234567', 'House with yard', 92, 'approved', '2026-09-28', '2026-09-30'),
    (3, 'Arif Hossain',  'arif.h@yahoo.com',        '+880 1819-445201', 'Apartment',       78, 'pending',  '2026-09-29', NULL),
    (2, 'Maria Lopez',   'maria.lopez@outlook.com', '+1 415 555 0192',  'Apartment',       85, 'pending',  '2026-09-29', NULL),
    (4, 'James Carter',  'jcarter@gmail.com',       '+1 415 555 0144',  'Shared flat',     54, 'rejected', '2026-09-25', '2026-09-27'),
    (5, 'Farhana Akter', 'farhana.akter@gmail.com', '+880 1552-908133', 'House',           88, 'pending',  '2026-09-30', NULL),
    (6, 'David Kim',     'david.kim@gmail.com',     '+1 650 555 0110',  'House with yard', 71, 'review',   '2026-09-27', NULL),
    (8, 'Sadia Islam',   'sadia.i@gmail.com',       '+880 1913-772018', 'Apartment',       81, 'review',   '2026-09-26', NULL);

INSERT INTO boarding (pet_name, species, breed, owner_name, owner_phone, check_in, check_out, kennel, status) VALUES
    ('Luna',    'cat', 'Siamese',  'Sadia Islam',  '+880 1913-772018', '2026-10-01', '2026-10-06', 'C-04', 'confirmed'),
    ('Max',     'dog', 'Beagle',   'Rafiq Uddin',  '+880 1712-556610', '2026-09-26', '2026-10-03', 'D-11', 'checked_in'),
    ('Simba',   'cat', 'Persian',  'Emily Davis',  '+1 415 555 0177',  '2026-10-01', '2026-10-04', 'C-02', 'pending'),
    ('Charlie', 'dog', 'Shih Tzu', 'Mahmud Hasan', '+880 1815-330921', '2026-10-01', '2026-10-10', 'D-03', 'pending'),
    ('Daisy',   'dog', 'Corgi',    'Anika Roy',    '+880 1678-110245', '2026-09-20', '2026-09-27', 'D-07', 'completed'),
    ('Tiger',   'cat', 'Bengal',   'Kamal Ahmed',  '+880 1511-908876', '2026-10-05', '2026-10-08', 'C-09', 'cancelled');

INSERT INTO quiz_questions (question_text, category, pet_type, difficulty, status, options, recommended_option) VALUES
    ('What type of home do you live in?', 'living_situation', 'all', 'easy', 'active',
     '["House with a yard", "House without a yard", "Apartment", "Shared flat"]', 0),
    ('How many hours per day are you home?', 'time_commitment', 'all', 'medium', 'active',
     '["Less than 4 hours", "4 to 8 hours", "More than 8 hours", "It changes every day"]', 2),
    ('Are there other pets currently living in your household?', 'home_environment', 'all', 'easy', 'active',
     '["No other pets", "Yes, and they are friendly with other animals", "Yes, but I am not sure how they get on with others", "Yes, and they prefer to be alone"]', 1),
    ('Have you previously trained or owned retriever breeds?', 'pet_experience', 'dog', 'hard', 'active',
     '["Yes, I have trained one myself", "Yes, but a trainer did most of the work", "No, but I have owned other dogs", "No, this would be my first dog"]', 0),
    ('Do you have a secure, fully fenced-in yard?', 'living_situation', 'dog', 'easy', 'active',
     '["Yes, fully fenced", "Partly fenced", "No, but there is a park nearby", "No outdoor space"]', 0),
    ('How will you handle your cat''s scratching needs?', 'pet_care', 'cat', 'medium', 'draft',
     '["Provide scratching posts around the home", "Trim nails regularly", "Declaw the cat", "Let it scratch the furniture"]', 0),
    ('Who will care for the pet when you travel?', 'time_commitment', 'all', 'medium', 'active',
     '["A family member who lives with me", "A trusted friend or pet sitter", "A boarding service", "I have not planned for this yet"]', 1),
    ('Can you provide a hutch with space to hop and stretch?', 'home_environment', 'rabbit', 'hard', 'draft',
     '["Yes, a large hutch plus daily free-roam time", "Yes, a large hutch", "A small cage only", "Not yet"]', 0);
