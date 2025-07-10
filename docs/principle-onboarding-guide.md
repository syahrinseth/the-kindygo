# Principal Onboarding Guide: Child Enrollment and Invoice Management

## Overview

This guide provides step-by-step instructions for Principals to onboard new children into the KindyGo system, including reviewing parent and child information, creating child enrollments, and generating invoices.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Step 1: Review Parent Information](#step-1-review-parent-information)
3. [Step 2: Review Child Information](#step-2-review-child-information)
4. [Step 3: Create Child Enrollment](#step-3-create-child-enrollment)
5. [Step 4: Generate Invoice](#step-4-generate-invoice)
6. [Best Practices](#best-practices)
7. [Troubleshooting](#troubleshooting)
8. [FAQ](#faq)

## Prerequisites

Before starting the onboarding process, ensure you have:

- [ ] Principal access to the KindyGo system
- [ ] Appropriate permissions to view/edit child enrollments
- [ ] Access to your centre's enrollment management
- [ ] Complete parent and child documentation

## Step 1: Review Parent Information

### 1.1 Access Parent Records

1. Navigate to **Users** section in the admin panel
2. Search for the parent by name, email, or phone number
3. Click on the parent's profile to review their information

### 1.2 Verify Parent Details

Ensure the following information is complete and accurate:

- **Personal Information**
  - Full name
  - Email address
  - Phone number
  - Address
  - Emergency contact details

- **Account Status**
  - Account is active
  - Email is verified
  - Profile is complete

### 1.3 Update Parent Information (if needed)

If any information is missing or incorrect:

1. Click **Edit** on the parent's profile
2. Update the necessary fields
3. Save the changes
4. Verify the updates are reflected correctly

## Step 2: Review Child Information

### 2.1 Access Child Records

1. From the parent's profile, navigate to the **Children** section
2. Or go to **Children** in the main navigation and search for the child

### 2.2 Verify Child Details

Ensure the following information is complete:

- **Basic Information**
  - Full name
  - Date of birth
  - Gender
  - Medical information (allergies, conditions, etc.)
  - Emergency contacts

- **Relationship to Parent**
  - Verify the child is properly linked to the correct parent/guardian
  - Check if multiple guardians are listed

### 2.3 Update Child Information (if needed)

If any information needs updating:

1. Click **Edit** on the child's profile
2. Update the necessary fields
3. Save the changes

## Step 3: Create Child Enrollment

### 3.1 Navigate to Child Enrollments

1. Go to **Child Enrollments** in the admin panel
2. Click **Create New Enrollment**

### 3.2 Fill Enrollment Details

Complete the following fields:

- **Child Selection**
  - Select the child from the dropdown
  - Verify the correct child is selected

- **Centre Information**
  - Centre: Select your centre
  - Product/Program: Choose the appropriate program
  - Enrollment Type: Select based on the child's needs

- **Billing Information**
  - Billing Frequency: Select from Monthly, Weekly, Daily, or One-time
  - Start Date: Set the enrollment start date (e.g., August 1st)
  - End Date: Set if applicable

- **Additional Products** (if applicable)
  - Add any additional services or products
  - Set billing frequency for each additional product

### 3.3 Enrollment Status

- Set status to **PENDING** initially
- The system will automatically change to **ACTIVE** when the first invoice is generated

### 3.4 Save and Review

1. Click **Save** to create the enrollment
2. Review the enrollment details for accuracy
3. Make any necessary corrections

## Step 4: Generate Invoice

### 4.1 Access the Enrollment

1. Navigate to **Child Enrollments**
2. Find the newly created enrollment
3. Click on the enrollment row to view details

### 4.2 Generate Invoice

1. Click the **Actions** button (three dots) on the enrollment row
2. Select **Generate Invoices**
3. Review the confirmation dialog which shows:
   - Number of enrollments that will be invoiced
   - Parent name
   - Centre name
   - Children names included

### 4.3 Confirm Invoice Generation

1. Click **Generate Invoice** to confirm
2. The system will:
   - Group all enrollments for the same parent at the same centre
   - Create a single invoice containing all applicable enrollments
   - Set invoice date to the earliest enrollment start date
   - Set due date to 7 days from the invoice date
   - Update enrollment status to **ACTIVE**

### 4.4 Verify Invoice Creation

1. Navigate to **Invoices** section
2. Find the newly generated invoice
3. Verify the invoice contains:
   - Correct parent information
   - All children's enrollments for that parent at your centre
   - Accurate pricing and billing periods
   - Proper due date (7 days from invoice date)

## Best Practices

### Enrollment Management

- **Review Before Creating**: Always verify parent and child information before creating enrollments
- **Consistent Start Dates**: Use consistent start dates (e.g., 1st of the month) for easier billing management
- **Document Additional Services**: Clearly document any additional products or services in the enrollment

### Invoice Generation

- **Group Billing**: The system automatically groups enrollments by parent and centre - one invoice per parent per centre
- **Timing**: Generate invoices close to the enrollment start date to ensure accurate billing periods
- **Review Before Sending**: Always review generated invoices before sending to parents

### Data Quality

- **Complete Profiles**: Ensure all parent and child profiles are complete before enrollment
- **Regular Updates**: Keep contact information up to date
- **Emergency Contacts**: Verify emergency contact information is current

## Troubleshooting

### Common Issues and Solutions

#### Issue: Cannot Find Parent in System
**Solution**: 
- Check if parent has registered an account
- Search using different criteria (email, phone, partial name)
- Create a new parent profile if necessary

#### Issue: Child Not Linked to Parent
**Solution**:
- Edit the child's profile
- Add the parent relationship
- Verify the relationship is saved correctly

#### Issue: "No Invoices Needed" Message
**Solution**:
- Check if invoices already exist for the billing period
- Verify enrollment start dates
- Ensure enrollment status is ACTIVE

#### Issue: Multiple Invoices Generated for Same Parent
**Solution**:
- This should not happen - the system groups by parent and centre
- Contact system administrator if this occurs
- Check if enrollments are at different centres

### When to Contact Support

Contact technical support if you encounter:
- System errors during enrollment creation
- Invoice generation failures
- Data synchronization issues
- Permission-related problems

## FAQ

### Q: Can I create multiple enrollments for the same child?
**A:** Yes, a child can have multiple enrollments for different programs or time periods, but ensure dates don't overlap inappropriately.

### Q: What happens if I generate an invoice twice?
**A:** The system prevents duplicate invoices. You'll see a "No Invoices Needed" message if invoices already exist for the billing period.

### Q: Can I modify an enrollment after creating an invoice?
**A:** Yes, but be cautious as it may affect billing. Consider creating a new enrollment for significant changes.

### Q: How does billing work for siblings?
**A:** All enrollments for children of the same parent at the same centre are grouped into a single invoice.

### Q: What if a parent has children at multiple centres?
**A:** Separate invoices will be generated for each centre.

### Q: Can I set different billing frequencies for additional products?
**A:** Yes, each additional product can have its own billing frequency independent of the main enrollment.

### Q: What's the difference between enrollment status PENDING and ACTIVE?
**A:** PENDING means the enrollment is created but not yet invoiced. ACTIVE means the first invoice has been generated and the enrollment is fully active.

### Q: How are invoice due dates calculated?
**A:** Due dates are set to 7 days from the invoice date (which is based on the enrollment start date).

## Support Contacts

For technical issues or questions not covered in this guide:

- **System Administrator**: [admin@kindygo.com]
- **Help Desk**: [support@kindygo.com]
- **Training Support**: [training@kindygo.com]

---

**Document Version**: 1.0  
**Last Updated**: July 2025  
**Next Review**: October 2025
