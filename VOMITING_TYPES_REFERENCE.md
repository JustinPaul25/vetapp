# Vomiting and Diarrhea Types Reference Guide

This document provides a reference guide for the different types of vomiting and diarrhea symptoms that can be selected in the veterinary application. Each type has specific clinical meanings that can help in diagnosis.

> **Note:** For diarrhea types, see [DIARRHEA_TYPES_REFERENCE.md](./DIARRHEA_TYPES_REFERENCE.md)

## Vomiting Types

## Vomiting Types

### 1. **Vomiting - Clear/Watery**
- **Description:** Often just water or saliva
- **Possible Causes:** 
  - Empty stomach
  - Drinking too much water
  - Early stage of vomiting episode

### 2. **Vomiting - White Foam**
- **Description:** Stomach acid mixed with air
- **Possible Causes:**
  - Acid reflux
  - Empty stomach
  - Indigestion
  - Gastritis

### 3. **Vomiting - Yellow/Green**
- **Description:** Contains bile
- **Possible Causes:**
  - Empty stomach (bile reflux)
  - Reflux
  - Eating grass (green color)
  - Intestinal issues

### 4. **Vomiting - Brown**
- **Description:** Digested food, or potentially digested blood if dark
- **Possible Causes:**
  - Normal digested food
  - **Dark brown/coffee grounds appearance:** Digested blood (upper GI bleed, ulcers) - **Requires immediate attention**

### 5. **Vomiting - Chunky/Undigested Food**
- **Description:** Contains visible food particles
- **Possible Causes:**
  - Eating too quickly
  - Regurgitation (if it's the whole meal)
  - Gastric motility issues
  - Obstruction (if persistent)

### 6. **Vomiting - Slimy/Mucus**
- **Description:** Contains mucus or slimy substance
- **Possible Causes:**
  - Excess drool
  - Inflammation
  - Parasites
  - Spoiled food ingestion
  - Gastritis

### 7. **Vomiting - Bloody (Red)**
- **Description:** Contains fresh, red blood
- **Possible Causes:**
  - **EMERGENCY SITUATION**
  - Fresh bleeding from throat or stomach
  - Trauma
  - Ulcers
  - Foreign body injury
  - **Requires immediate veterinary attention**

### 8. **Vomiting - Bloody (Black)**
- **Description:** Contains digested blood (appears black/dark)
- **Possible Causes:**
  - **EMERGENCY SITUATION**
  - Upper GI bleed
  - Ulcers
  - Severe gastritis
  - **Requires immediate veterinary attention**

### 9. **Vomiting - Worms**
- **Description:** Visible worms in vomit
- **Possible Causes:**
  - **Serious parasite infestation** (e.g., roundworms)
  - Heavy worm burden
  - Requires deworming treatment
  - May indicate severe parasitic infection

## General Notes

- **General "Vomiting"** symptom is also available for cases where the specific type is not identified or when documenting general vomiting episodes.
- Bloody vomiting (red or black) and vomiting with worms are **emergency situations** that require immediate veterinary attention.
- Multiple vomiting types can be selected if the patient exhibits different types during the episode or over time.
- These specific vomiting types help improve the accuracy of disease prediction models by providing more detailed symptom information.

## Usage in the Application

These vomiting types are available as selectable symptoms in:
- Prescription creation
- Disease management
- Patient examination records
- Machine learning disease prediction models

The detailed vomiting type information helps the ML models make more accurate disease predictions by correlating specific vomiting characteristics with known disease patterns.

